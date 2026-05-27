<?php

declare(strict_types=1);

/*
|------------------------------------------------------------------------------
| Donation link (single source of truth)
|------------------------------------------------------------------------------
| SysRevAI is free and open source, maintained by a researcher in their spare
| time. This is the ONLY place the donation URL is defined. If a downstream
| fork wants to point to a different sponsor, change it here — it is NOT a
| database-editable setting on purpose (keeping minimal author visibility is
| part of the open-source agreement).
|
| Rules enforced across the UI (see views/partials/donate_link.php):
|   - Never a pop-up, modal or overlay.
|   - Never shown on the login screen.
|   - Never gates any functionality.
|   - The footer link is always present; only the dashboard mention is toggleable.
*/

if (!defined('DONATE_URL')) {
    define('DONATE_URL', 'https://donate.stripe.com/28EaEY6ML1FI7HH1El7wA02');
}
