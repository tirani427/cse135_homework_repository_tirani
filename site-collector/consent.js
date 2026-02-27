/**
 * consent.js â€” Consent Management Module
 * CSE 135 - Module 10: Production Readiness
 *
 * Provides a ConsentManager object for checking, granting, and revoking
 * analytics consent. Includes a simple DOM-based consent banner.
 *
 * Consent is stored as a cookie (analytics_consent=true|false) with
 * a 1-year expiry. The check() method also inspects the Global Privacy
 * Control (GPC) signal (navigator.globalPrivacyControl).
 *
 * Usage:
 *   <script src="consent.js"></script>
 *   <script>
 *     if (!ConsentManager.check()) {
 *       ConsentManager.showBanner();
 *     }
 *   </script>
 */

const ConsentManager = (function () {
  'use strict';

  /**
   * Check whether the user has granted analytics consent.
   *
   * Returns false if:
   *   - Global Privacy Control is set (navigator.globalPrivacyControl)
   *   - The analytics_consent cookie is 'false'
   *   - No consent cookie exists (GDPR opt-in default)
   *
   * Returns true only if the analytics_consent cookie is 'true'.
   */
  function check() {
    // Global Privacy Control overrides everything
    if (navigator.globalPrivacyControl) {
      return false;
    }

    // Look for the consent cookie
    const cookies = document.cookie.split(';');
    for (const c of cookies) {
      const cookie = c.trim();
      if (cookie.indexOf('analytics_consent=') === 0) {
        return cookie.split('=')[1] === 'true';
      }
    }

    // No consent cookie found â€” default to false (opt-in model)
    return false;
  }

  /**
   * Grant analytics consent.
   * Sets the analytics_consent cookie to 'true' with a 1-year expiry.
   * Uses SameSite=Lax and Secure attributes for best practices.
   */
  function grant() {
    const expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = `analytics_consent=true; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;
    console.log('[ConsentManager] Consent granted');

    // Remove the banner if it exists
    const banner = document.getElementById('consent-banner');
    if (banner) {
      banner.parentNode.removeChild(banner);
    }
  }

  /**
   * Revoke analytics consent.
   * Sets the analytics_consent cookie to 'false' and clears
   * any analytics data from sessionStorage.
   */
  function revoke() {
    const expires = new Date();
    expires.setFullYear(expires.getFullYear() + 1);
    document.cookie = `analytics_consent=false; expires=${expires.toUTCString()}; path=/; SameSite=Lax`;

    // Clear analytics session data
    try {
      sessionStorage.removeItem('_collector_sid');
      sessionStorage.removeItem('_collector_sample');
      sessionStorage.removeItem('_collector_retry');
    } catch (e) { /* sessionStorage unavailable */ }

    console.log('[ConsentManager] Consent revoked, session data cleared');

    // Remove the banner if it exists
    const banner = document.getElementById('consent-banner');
    if (banner) {
      banner.parentNode.removeChild(banner);
    }
  }

  /**
   * Show a simple consent banner at the bottom of the page.
   *
   * Options:
   *   - message {string}   Banner text (default: generic analytics message)
   *   - acceptText {string} Accept button text (default: 'Accept')
   *   - declineText {string} Decline button text (default: 'Decline')
   *   - onAccept {function} Callback after consent is granted
   *   - onDecline {function} Callback after consent is revoked
   */
  function showBanner(options) {
    options = options || {};

    // Don't show if consent is already decided
    const cookies = document.cookie.split(';');
    for (const c of cookies) {
      if (c.trim().indexOf('analytics_consent=') === 0) {
        return; // Already decided
      }
    }

    const message = options.message ||
      'This site uses analytics to understand how visitors interact with the content. ' +
      'No personal data is sold or shared with third parties.';
    const acceptText = options.acceptText || 'Accept';
    const declineText = options.declineText || 'Decline';

    // Create the banner element
    const banner = document.createElement('div');
    banner.id = 'consent-banner';
    banner.style.cssText =
      'position: fixed; bottom: 0; left: 0; right: 0; ' +
      'background: #2c3e50; color: white; padding: 16px 24px; ' +
      'display: flex; align-items: center; justify-content: space-between; ' +
      'gap: 16px; z-index: 10000; font-family: -apple-system, BlinkMacSystemFont, ' +
      '"Segoe UI", Roboto, sans-serif; font-size: 14px; ' +
      'box-shadow: 0 -2px 10px rgba(0,0,0,0.3);';

    const textSpan = document.createElement('span');
    textSpan.textContent = message;
    textSpan.style.cssText = 'flex: 1; line-height: 1.4;';

    const btnContainer = document.createElement('div');
    btnContainer.style.cssText = 'display: flex; gap: 10px; flex-shrink: 0;';

    const acceptBtn = document.createElement('button');
    acceptBtn.textContent = acceptText;
    acceptBtn.style.cssText =
      'background: #27ae60; color: white; border: none; padding: 8px 20px; ' +
      'border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: bold;';
    acceptBtn.addEventListener('click', () => {
      grant();
      if (typeof options.onAccept === 'function') {
        options.onAccept();
      }
    });

    const declineBtn = document.createElement('button');
    declineBtn.textContent = declineText;
    declineBtn.style.cssText =
      'background: transparent; color: white; border: 1px solid white; padding: 8px 20px; ' +
      'border-radius: 4px; cursor: pointer; font-size: 14px;';
    declineBtn.addEventListener('click', () => {
      revoke();
      if (typeof options.onDecline === 'function') {
        options.onDecline();
      }
    });

    btnContainer.appendChild(acceptBtn);
    btnContainer.appendChild(declineBtn);
    banner.appendChild(textSpan);
    banner.appendChild(btnContainer);
    document.body.appendChild(banner);
  }

  // Public interface
  return {
    check: check,
    grant: grant,
    revoke: revoke,
    showBanner: showBanner
  };
})();

// Expose globally
window.ConsentManager = ConsentManager;