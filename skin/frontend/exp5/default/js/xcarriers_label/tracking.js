/**
 * XFE LogisticsCarriersLabel - Tracking Copy Functionality
 *
 * Provides clipboard copy functionality for partner tracking numbers.
 */

/**
 * Copy partner tracking number to clipboard
 *
 * @param {HTMLElement} btn - The clicked copy button element
 * @param {string} trackingNumber - The tracking number to copy
 */
function copyPartnerTracking(btn, trackingNumber) {
    if (!trackingNumber) return;

    // Use modern clipboard API if available
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(trackingNumber).then(function() {
            showCopySuccess(btn);
        }).catch(function() {
            fallbackCopy(btn, trackingNumber);
        });
    } else {
        fallbackCopy(btn, trackingNumber);
    }
}

/**
 * Fallback copy method using textarea
 */
function fallbackCopy(btn, text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();

    try {
        var successful = document.execCommand('copy');
        if (successful) {
            showCopySuccess(btn);
        }
    } catch (e) {
        // Copy failed silently
    }

    document.body.removeChild(textarea);
}

/**
 * Show copy success indicator
 */
function showCopySuccess(btn) {
    var container = btn.parentNode;
    if (!container) return;

    var successEl = container.querySelector('.copy-success');
    if (successEl) {
        successEl.style.display = 'inline';
        setTimeout(function() {
            successEl.style.display = 'none';
        }, 2000);
    }
}
