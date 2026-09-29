# Account passwords

Account passwords require at least 12 characters, including at least one lowercase letter, one uppercase letter, and one number. Symbols are optional. Unicode letters and numbers are accepted.

Registration, account creation from an organization invitation, and **Change password** display the same checklist. Each requirement changes from × to ✓ as it is satisfied, and the confirmation field reports whether both passwords match. Deleting characters updates the checklist too. The checklist runs locally in the browser; it does not send or store passwords. Without JavaScript, the requirements remain visible and the server validates the form on submission.

Signed-in users can select **Change password** beside **Log out**, or visit `/account/password`. They must enter their current password and confirm the new one. The update is limited to the signed-in account, rate-limited, and protected by Laravel's authentication and CSRF middleware. A successful change rotates the remember token and regenerates the current session ID.

`App\Support\Auth\AccountPasswordPolicy::rule()` defines the policy for all three flows. The Blade component derives its checklist and password-manager hints from that same rule. This account policy does not apply to gift-card or appointment-page access passwords.

No database migration or front-end build is needed. Deploy the new JavaScript asset and clear compiled views and routes with `php artisan optimize:clear`.
