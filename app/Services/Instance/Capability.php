<?php

namespace App\Services\Instance;

/**
 * Something this installation might do that reaches beyond itself. A copy of the shop asks SafeMode
 * before it does any of these, and the answer depends only on the mode.
 */
enum Capability: string
{
    /** Deliver an email to a real address (the allow-list of a staging copy is a separate, finer rule). */
    case SendMail = 'send_mail';

    /** Start a card payment with a gateway (a staging copy may, but only against the gateway's sandbox). */
    case ChargeCard = 'charge_card';

    /** Use the Paysera gateway, which has no sandbox in this application. */
    case UsePaysera = 'use_paysera';

    /** Capture or release a payment that already exists (a copied payment is refused separately). */
    case SettlePayment = 'settle_payment';

    /** Tell search engines or a CDN something changed: IndexNow, Google and Bing pings, Cloudflare. */
    case NotifySearchEngines = 'notify_search_engines';

    /** Be crawled and indexed: robots.txt, meta robots, sitemap, llms.txt. */
    case BeIndexed = 'be_indexed';

    /** Send a browser push notification. */
    case SendWebPush = 'send_web_push';

    /** Install an update on its own. */
    case AutoUpdate = 'auto_update';

    /** Write backups to a disk shared with other sites (run ids restart at 1 on a copy). */
    case UseRemoteBackupDisk = 'use_remote_backup_disk';

    /** Run scheduled work that only touches this installation (housekeeping, snapshots, backups). */
    case RunInternalSchedule = 'run_internal_schedule';

    /** Run scheduled work that reaches people or money. */
    case RunCustomerSchedule = 'run_customer_schedule';
}
