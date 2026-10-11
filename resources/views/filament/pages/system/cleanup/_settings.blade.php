{{-- Engine-wide settings. Per-target retention and the automatic toggle live on each target's row. --}}
<div class="oc-card">
    <div class="oc-card-head"><div class="oc-h">Automatic cleaning &amp; safety settings</div></div>
    <p class="oc-sub">
        When automatic cleaning is on, every target whose "Automatic" switch is on is cleaned about once a day, only inside the time window below,
        and only while no backup or update is running. The first automatic run of a target is always a preview, so nothing is deleted on the first night.
        A target that would delete more of its table than the safety limit allows is skipped and you are notified.
    </p>

    <form wire:submit="saveEngineSettings">
        <div class="oc-form">
            <div class="oc-field">
                <label>Automatic cleaning</label>
                <label style="display:flex;align-items:center;gap:.5rem;font-weight:400;cursor:pointer;">
                    <button type="button" class="oc-switch {{ $autoEnabled ? 'on' : '' }}" wire:click="$toggle('autoEnabled')" role="switch" aria-checked="{{ $autoEnabled ? 'true' : 'false' }}"></button>
                    {{ $autoEnabled ? 'On' : 'Off' }}
                </label>
                <div class="oc-muted">Needs the server's scheduler (cron) to be running.</div>
            </div>
            <div class="oc-field">
                <label for="oc-win-start">Allowed from (time)</label>
                <input id="oc-win-start" class="oc-in oc-in-wide" type="text" wire:model="windowStart" placeholder="02:00">
                @error('windowStart')<div class="oc-err">{{ $message }}</div>@enderror
            </div>
            <div class="oc-field">
                <label for="oc-win-end">Until (time)</label>
                <input id="oc-win-end" class="oc-in oc-in-wide" type="text" wire:model="windowEnd" placeholder="06:00">
                @error('windowEnd')<div class="oc-err">{{ $message }}</div>@enderror
                <div class="oc-muted">Your store's local time. The nightly backup runs at {{ settings('backup.schedule_time', config('backup.schedule.time', '01:00')) }}.</div>
            </div>
            <div class="oc-field">
                <label for="oc-maxpct">Safety limit for automatic runs (%)</label>
                <input id="oc-maxpct" class="oc-in oc-in-wide" type="number" min="1" max="100" wire:model="maxDeletePct">
                @error('maxDeletePct')<div class="oc-err">{{ $message }}</div>@enderror
                <div class="oc-muted">An automatic run skips a table when it would delete more than this share of it.</div>
            </div>
            <div class="oc-field">
                <label for="oc-chunk">Rows per step</label>
                <input id="oc-chunk" class="oc-in oc-in-wide" type="number" min="200" max="10000" wire:model="chunkRows">
                @error('chunkRows')<div class="oc-err">{{ $message }}</div>@enderror
                <div class="oc-muted">Smaller is gentler on a busy database. It adapts by itself within this ceiling.</div>
            </div>
            <div class="oc-field">
                <label for="oc-sleep">Pause between steps (ms)</label>
                <input id="oc-sleep" class="oc-in oc-in-wide" type="number" min="0" max="2000" wire:model="sleepMs">
                @error('sleepMs')<div class="oc-err">{{ $message }}</div>@enderror
            </div>
            <div class="oc-field">
                <label for="oc-hist">Keep cleanup history (days)</label>
                <input id="oc-hist" class="oc-in oc-in-wide" type="number" min="30" max="3650" wire:model="historyDays">
                @error('historyDays')<div class="oc-err">{{ $message }}</div>@enderror
            </div>
            <div class="oc-field">
                <label for="oc-disk">Warn when the disk is this full (%)</label>
                <input id="oc-disk" class="oc-in oc-in-wide" type="number" min="50" max="99" wire:model="diskAlertPct">
                @error('diskAlertPct')<div class="oc-err">{{ $message }}</div>@enderror
            </div>
            <div class="oc-field">
                <label>Export rows before deleting</label>
                <label style="display:flex;align-items:center;gap:.5rem;font-weight:400;cursor:pointer;">
                    <button type="button" class="oc-switch {{ $exportBeforeDelete ? 'on' : '' }}" wire:click="$toggle('exportBeforeDelete')" role="switch" aria-checked="{{ $exportBeforeDelete ? 'true' : 'false' }}"></button>
                    {{ $exportBeforeDelete ? 'On' : 'Off' }}
                </label>
                <div class="oc-muted">Saves a compressed CSV of what is removed (kept 30 days). It can contain personal data; large tables are skipped rather than exported.</div>
            </div>
            <div class="oc-field">
                <label for="oc-notify">Tell admins about automatic runs that…</label>
                <select id="oc-notify" class="oc-in oc-in-wide" style="width:100%;" wire:model="notifyOn">
                    <option value="partial_failed">did not finish cleanly</option>
                    <option value="failed">failed</option>
                    <option value="never">never (don't notify)</option>
                </select>
            </div>
        </div>
        <div style="margin-top:1rem;">
            <button type="submit" class="oc-btn oc-btn-primary" wire:loading.attr="disabled" wire:target="saveEngineSettings">Save settings</button>
        </div>
    </form>
</div>
