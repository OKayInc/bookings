# M9-R6 — global page loading indicator

M9-R6 adds one loading experience to both authenticated and public pages without introducing a frontend framework or asset build step.

## Loading behaviour

- A full-screen overlay appears for same-origin link navigation and valid same-origin form submissions.
- The loader also covers the remaining asset-loading interval on a direct visit after Laravel has delivered the layout markup.
- Browser reload and back/forward lifecycle events are handled without `beforeunload`, preserving eligibility for the browser back-forward cache.
- `pageshow` and `load` always clear stale loader state.
- Cancelled confirmation prompts, prevented events, HTML validation failures, external links, modified clicks, new tabs, same-page fragments, `mailto:` links and downloads do not activate the overlay.
- `data-page-loader-ignore` is available as an explicit opt-out for future controls that do not perform a normal navigation.

The indicator uses application CSS rather than Bootstrap's spinner markup, so it remains understandable while external Bootstrap assets load. It exposes a live status message, applies `aria-busy` to the page during loading and respects reduced-motion preferences.

The browser cannot display application HTML while waiting for the server's first response byte. Therefore, this improves navigation feedback but does not conceal or solve a slow Laravel response before markup arrives.

## Related fixes and optimizations

- Existing contract, signed-file and questionnaire-file links are now marked as downloads, preventing a non-navigation response from leaving the overlay visible.
- Both layouts preconnect to `cdn.jsdelivr.net`, allowing the browser to begin DNS, TCP and TLS setup before requesting Bootstrap.
- The dashboard's resource, appointment-type and member totals now use one Eloquent aggregate query rather than three independent count queries.
- The application stylesheet and loader script carry an M9-R6 cache version so existing browsers receive the new assets immediately.
- Runtime release metadata now reports M9-R6 instead of the stale M9-R1 identifier.

No database migration, Composer dependency, JavaScript dependency or asset compilation is required.
