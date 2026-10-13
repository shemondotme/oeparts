#!/usr/bin/env python3
"""
An independent reader for .oeparts v1 packages.

It shares NO code with the PHP writer or reader: it is written from the format description alone
and uses other libraries (Python `cryptography` for AES-GCM/HKDF, `argon2-cffi` for Argon2id,
hashlib for PBKDF2 and zlib for gzip). If this script and the PHP code agree on a package, the
format is genuinely specified and not just self-consistent.

    python independent_reader.py FILE PASSPHRASE [--secrets]

prints one line of JSON ({"ok": true, ...} or {"ok": false, "error": ...}) and exits 0 / 1.
Requires:  pip install cryptography argon2-cffi
"""
import hashlib
import hmac
import json
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


def gunzip_members(blob):
    """Every gzip member, not only the first (zlib.decompress and gzip.decompress differ here)."""
    out = b""
    while blob:
        decoder = zlib.decompressobj(16 + zlib.MAX_WBITS)
        out += decoder.decompress(blob)
        if not decoder.eof:
            fail("truncated gzip member")
        blob = decoder.unused_data
    return out


def crockford_fingerprint(mac):
    value = int.from_bytes(mac[:10], "big")
    chars = "".join(CROCKFORD[(value >> shift) & 31] for shift in range(75, -1, -5))
    return "-".join(chars[i:i + 4] for i in range(0, 16, 4))


def main(path, passphrase, want_secrets):
    data = open(path, "rb").read()

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

    block = 1 << block_log2
    entries = {}
    offset = header_len

    while True:
        head = data[offset:offset + 24]
        if head[:4] != b"OEPE" or head[9:12] != b"\x00\x00\x00":
            fail("bad entry header at %d" % offset)
        entry_id, = struct.unpack(">I", head[4:8])
        key_class = head[8]
        frame_count, = struct.unpack(">I", head[12:16])
        plain_len, = struct.unpack(">Q", head[16:24])

        key = {0: k_data, 1: k_secrets}[key_class]
        plain = b""
        cursor = offset + 24

        for seq in range(frame_count):
            length = block if seq < frame_count - 1 else plain_len - (frame_count - 1) * block
            iv, tag = data[cursor:cursor + 12], data[cursor + 12:cursor + 28]
            declared, = struct.unpack(">I", data[cursor + 28:cursor + 32])
            if declared != length:
                fail("frame length mismatch")
            ciphertext = data[cursor + 32:cursor + 32 + length]
            aad = b"OEP1\x00" + package_id + struct.pack(">IBIIQ", entry_id, key_class, seq, frame_count, plain_len)
            plain += AESGCM(key).decrypt(iv, ciphertext + tag, aad)
            cursor += 32 + length

        entries[entry_id] = {"offset": offset, "enc_len": cursor - offset, "key_class": key_class, "plain": plain}
        offset = cursor

        if entry_id == TOC_ID:
            break

    trailer = data[-64:]
    toc_offset, toc_len = struct.unpack(">QQ", trailer[8:24])
    if trailer[:8] != b"OEPTRL01" or trailer[56:] != b"OEPARTS1":
        fail("bad trailer")
    if toc_offset != entries[TOC_ID]["offset"] or toc_len != entries[TOC_ID]["enc_len"] or offset != len(data) - 64:
        fail("trailer does not match the layout")
    if hashlib.sha256(data[:toc_offset + toc_len]).digest() != trailer[24:56]:
        fail("whole-file hash mismatch")

    toc_plain = entries[TOC_ID]["plain"]
    toc = json.loads(toc_plain)
    report = []

    for record in toc["entries"]:
        entry = entries[record["id"]]
        stored = entry["plain"]
        if hashlib.sha256(stored).hexdigest() != record["plain_sha256"]:
            fail("content hash mismatch for " + record["name"])
        decoded = gunzip_members(stored) if record["codec"] == "gzip" else stored
        if len(decoded) != record["raw_len"]:
            fail("decoded length mismatch for " + record["name"])

        item = {
            "id": record["id"],
            "name": record["name"],
            "key_class": entry["key_class"],
            "codec": record["codec"],
            "stored_len": len(stored),
            "decoded_len": len(decoded),
            "decoded_sha256": hashlib.sha256(decoded).hexdigest(),
        }
        if entry["key_class"] == 1 and want_secrets:
            item["decoded_text"] = decoded.decode()
        report.append(item)

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


if __name__ == "__main__":
    if len(sys.argv) < 3:
        fail("usage: independent_reader.py FILE PASSPHRASE [--secrets]")
    main(sys.argv[1], sys.argv[2], "--secrets" in sys.argv[3:])
