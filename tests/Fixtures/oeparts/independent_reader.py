#!/usr/bin/env python3
"""
An independent reader for .oeparts v1 packages.

It shares NO code with the PHP writer or reader: it is written from the format description alone
and uses other libraries (Python `cryptography` for AES-GCM/HKDF, `argon2-cffi` for Argon2id,
hashlib for PBKDF2 and zlib for gzip). If this script and the PHP code agree on a package, the
format is genuinely specified and not just self-consistent.

It works in constant memory (the file is memory-mapped and every entry is decrypted frame by
frame), so it can check a multi-gigabyte package.

    python independent_reader.py FILE PASSPHRASE [--secrets]

prints one line of JSON ({"ok": true, ...} or {"ok": false, "error": ...}) and exits 0 / 1.
Requires:  pip install cryptography argon2-cffi
"""
import hashlib
import hmac
import json
import mmap
import struct
import sys
import zlib

from argon2.low_level import Type, hash_secret_raw
from cryptography.hazmat.primitives import hashes
from cryptography.hazmat.primitives.ciphers.aead import AESGCM
from cryptography.hazmat.primitives.kdf.hkdf import HKDF

CROCKFORD = "0123456789ABCDEFGHJKMNPQRSTVWXYZ"
TOC_ID = 0xFFFFFFFF


def fail(message):
    print(json.dumps({"ok": False, "error": message}))
    sys.exit(1)


def subkey(master, header_auth, name):
    return HKDF(
        algorithm=hashes.SHA256(),
        length=32,
        salt=hashlib.sha256(header_auth).digest(),
        info=b"oeparts/v1/" + name,
    ).derive(master)


class GzipMembers:
    """Incremental gzip decoder that restarts at the end of every member (gzip.decompress would
    also do that, but not incrementally; zlib.decompress would stop after the first member)."""

    def __init__(self):
        self.decoder = zlib.decompressobj(16 + zlib.MAX_WBITS)
        self.pending = False

    def feed(self, blob):
        out = []
        while blob:
            out.append(self.decoder.decompress(blob))
            self.pending = True
            if self.decoder.eof:
                blob = self.decoder.unused_data
                self.decoder = zlib.decompressobj(16 + zlib.MAX_WBITS)
                self.pending = False
            else:
                blob = b""
        return b"".join(out)

    def finish(self):
        if self.pending:
            fail("truncated gzip member")


def crockford_fingerprint(mac):
    value = int.from_bytes(mac[:10], "big")
    chars = "".join(CROCKFORD[(value >> shift) & 31] for shift in range(75, -1, -5))
    return "-".join(chars[i:i + 4] for i in range(0, 16, 4))


def decrypt_entry(data, offset, expected, keys, package_id, block):
    """Yield the plaintext of one entry frame by frame, after checking its header."""
    head = data[offset:offset + 24]
    if head[:4] != b"OEPE" or head[9:12] != b"\x00\x00\x00":
        fail("bad entry header at %d" % offset)
    entry_id, = struct.unpack(">I", head[4:8])
    key_class = head[8]
    frame_count, = struct.unpack(">I", head[12:16])
    plain_len, = struct.unpack(">Q", head[16:24])

    if (entry_id, key_class, frame_count, plain_len) != expected:
        fail("entry header does not match the table of contents at %d" % offset)

    cursor = offset + 24
    for seq in range(frame_count):
        length = block if seq < frame_count - 1 else plain_len - (frame_count - 1) * block
        iv, tag = data[cursor:cursor + 12], data[cursor + 12:cursor + 28]
        declared, = struct.unpack(">I", data[cursor + 28:cursor + 32])
        if declared != length:
            fail("frame length mismatch")
        ciphertext = data[cursor + 32:cursor + 32 + length]
        aad = b"OEP1\x00" + package_id + struct.pack(">IBIIQ", entry_id, key_class, seq, frame_count, plain_len)
        yield AESGCM(keys[key_class]).decrypt(iv, ciphertext + tag, aad)
        cursor += 32 + length


def whole_file_sha256(data, end):
    digest = hashlib.sha256()
    for start in range(0, end, 1 << 22):
        digest.update(data[start:min(start + (1 << 22), end)])
    return digest.digest()


def main(path, passphrase, want_secrets):
    with open(path, "rb") as handle:
        data = mmap.mmap(handle.fileno(), 0, access=mmap.ACCESS_READ)

        if data[:8] != b"OEPARTS1":
            fail("bad magic")
        if data[8:12] != b"\r\n\x1a\n":
            fail("bad text-mode guard")

        major, minor, header_len = struct.unpack(">HHH", data[12:18])
        critical, ignorable = struct.unpack(">II", data[20:28])
        cipher, kdf, block_log2 = data[28], data[29], data[30]
        p1, p2 = struct.unpack(">II", data[32:40])
        salt, package_id = data[40:56], data[56:72]

        if major != 1 or critical != 0 or cipher != 1:
            fail("unsupported format")

        header_auth = data[:header_len - 32]
        keycheck = data[header_len - 32:header_len]

        if kdf == 1:
            master = hash_secret_raw(passphrase.encode(), salt, time_cost=p1, memory_cost=p2, parallelism=1, hash_len=32, type=Type.ID, version=19)
        elif kdf == 2:
            master = hashlib.pbkdf2_hmac("sha512", passphrase.encode(), salt, p1, 32)
        else:
            fail("unknown kdf")

        k_check, k_data, k_secrets, k_fp = (subkey(master, header_auth, n) for n in (b"check", b"data", b"secrets", b"fp"))

        expected = hmac.new(k_check, b"OEPARTS1/keycheck" + header_auth, hashlib.sha256).digest()
        if not hmac.compare_digest(expected, keycheck):
            fail("wrong passphrase")

        keys = {0: k_data, 1: k_secrets}
        block = 1 << block_log2
        size = len(data)

        # The trailer says where the table of contents is; the table of contents says where everything else is.
        trailer = data[size - 64:]
        toc_offset, toc_len = struct.unpack(">QQ", trailer[8:24])
        if trailer[:8] != b"OEPTRL01" or trailer[56:] != b"OEPARTS1":
            fail("bad trailer")
        if toc_offset + toc_len + 64 != size:
            fail("trailer does not match the file size")
        if whole_file_sha256(data, toc_offset + toc_len) != trailer[24:56]:
            fail("whole-file hash mismatch")

        toc_head = struct.unpack(">IBxxxIQ", data[toc_offset + 4:toc_offset + 24])
        toc_plain = b"".join(decrypt_entry(data, toc_offset, (TOC_ID, 0, toc_head[2], toc_head[3]), keys, package_id, block))
        toc = json.loads(toc_plain)

        report = []
        expected_offset = header_len

        for record in toc["entries"]:
            if record["offset"] != expected_offset:
                fail("entries are not contiguous at " + record["name"])
            expected_offset += record["enc_len"]

            stored_hash = hashlib.sha256()
            decoded_hash = hashlib.sha256()
            decoded_len = 0
            stored_len = 0
            text = b""
            gzip = GzipMembers() if record["codec"] == "gzip" else None

            frames = decrypt_entry(data, record["offset"], (record["id"], record["key_class"], record["frames"], record["plain_len"]), keys, package_id, block)
            for plain in frames:
                stored_hash.update(plain)
                stored_len += len(plain)
                decoded = gzip.feed(plain) if gzip else plain
                decoded_hash.update(decoded)
                decoded_len += len(decoded)
                if record["key_class"] == 1 and want_secrets:
                    text += decoded
            if gzip:
                gzip.finish()

            if stored_hash.hexdigest() != record["plain_sha256"]:
                fail("content hash mismatch for " + record["name"])
            if decoded_len != record["raw_len"]:
                fail("decoded length mismatch for " + record["name"])

            item = {
                "id": record["id"],
                "name": record["name"],
                "key_class": record["key_class"],
                "codec": record["codec"],
                "stored_len": stored_len,
                "decoded_len": decoded_len,
                "decoded_sha256": decoded_hash.hexdigest(),
            }
            if text:
                item["decoded_text"] = text.decode()
            report.append(item)

        if expected_offset != toc_offset:
            fail("entries do not end where the table of contents begins")

        mac = hmac.new(k_fp, hashlib.sha256(toc_plain).digest() + package_id, hashlib.sha256).digest()

        print(json.dumps({
            "ok": True,
            "format": "%d.%d" % (major, minor),
            "kdf": kdf,
            "block_log2": block_log2,
            "package_id": package_id.hex(),
            "fingerprint": crockford_fingerprint(mac),
            "file_sha256": trailer[24:56].hex(),
            "entries": sorted(report, key=lambda r: r["id"]),
        }, sort_keys=True))
        data.close()


if __name__ == "__main__":
    if len(sys.argv) < 3:
        fail("usage: independent_reader.py FILE PASSPHRASE [--secrets]")
    main(sys.argv[1], sys.argv[2], "--secrets" in sys.argv[3:])
