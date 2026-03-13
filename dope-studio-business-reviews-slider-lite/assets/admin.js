(function () {
  const fetchButtons = Array.from(document.querySelectorAll('.grs-fetch-btn[data-grs-fetch-scope]'));
  const enableGoogle = document.getElementById('grs_enable_google');
  const googleRatingMode = document.getElementById('grs_rating_mode_default');
  const googleReviewCountMode = document.getElementById('grs_review_count_mode');

  const toggleRow = (inputId, enabled, visible = true) => {
    const input = document.getElementById(inputId);
    if (!input) {
      return;
    }

    input.disabled = !enabled;
    const row = input.closest('tr');
    if (!row) {
      return;
    }

    if (enabled) {
      row.classList.remove('grs-row-disabled');
    } else {
      row.classList.add('grs-row-disabled');
    }

    if (visible) {
      row.classList.remove('grs-row-hidden');
    } else {
      row.classList.add('grs-row-hidden');
    }
  };

  const applyPlatformState = () => {
    const googleOn = !enableGoogle || enableGoogle.checked;

    [
      'grs_place_id',
      'grs_place_url',
      'grs_google_places_api_key',
      'grs_use_places_api_summary',
      'grs_max_reviews',
      'grs_language',
      'grs_cron_enabled',
      'grs_cron_frequency',
      'grs_cron_time',
      'grs_cron_fetch_scope',
      'grs_theme',
      'grs_show_no_comment',
      'grs_show_summary_default',
      'grs_show_read_on_google_default',
      'grs_rating_mode_default',
      'grs_display_limit_default',
      'grs_min_rating_default',
      'grs_review_count_mode',
      'grs_custom_review_count',
      'grs_title_default',
      'grs_autoplay_default',
      'grs_autoplay_interval_default',
      'grs_loop_infinite_default',
      'grs_show_dots_default',
      'grs_swipe_default',
      'grs_slides_mobile_default',
      'grs_slides_tablet_default',
      'grs_slides_desktop_default'
    ].forEach((id) => toggleRow(id, googleOn));

    const googleManualOn = googleOn && !!googleRatingMode && googleRatingMode.value === 'manual';
    const googleCustomCountOn = googleOn && !!googleReviewCountMode && googleReviewCountMode.value === 'custom';

    toggleRow('grs_manual_rating_default', googleManualOn, googleManualOn);
    toggleRow('grs_custom_review_count', googleCustomCountOn, googleCustomCountOn);
  };

  if (enableGoogle) {
    applyPlatformState();
    enableGoogle.addEventListener('change', applyPlatformState);

    if (googleRatingMode) {
      googleRatingMode.addEventListener('change', applyPlatformState);
    }

    if (googleReviewCountMode) {
      googleReviewCountMode.addEventListener('change', applyPlatformState);
    }
  }

  if (!fetchButtons.length || typeof dsbrslAdmin === 'undefined') {
    return;
  }

  const setStatus = (status, text, isError = false) => {
    if (!status) {
      return;
    }

    status.textContent = text || '';
    status.style.color = isError ? '#b32d2e' : '#50575e';
  };

  fetchButtons.forEach((fetchBtn) => {
    const scope = (fetchBtn.getAttribute('data-grs-fetch-scope') || 'google').toLowerCase();
    const status = fetchBtn.parentElement
      ? fetchBtn.parentElement.querySelector('.grs-fetch-status')
      : null;

    fetchBtn.addEventListener('click', async () => {
      fetchBtn.disabled = true;
      setStatus(status, 'Fetching reviews... This may take a few minutes. Please do not close this page.');

      try {
        const formData = new FormData();
        formData.append('action', dsbrslAdmin.action);
        formData.append('nonce', dsbrslAdmin.nonce);
        formData.append('scope', scope);

        const response = await fetch(dsbrslAdmin.ajaxUrl, {
          method: 'POST',
          body: formData,
          credentials: 'same-origin'
        });

        const payload = await response.json();

        if (!response.ok || !payload.success) {
          const message = payload?.data?.message || 'Fetch failed.';
          setStatus(status, message, true);
          fetchBtn.disabled = false;
          return;
        }

        setStatus(status, payload?.data?.message || 'Reviews fetched.');
        window.setTimeout(() => window.location.reload(), 700);
      } catch (error) {
        setStatus(status, 'Request failed. Check server logs / console.', true);
        fetchBtn.disabled = false;
        // eslint-disable-next-line no-console
        console.error(error);
      }
    });
  });
})();
