(function () {
  const wizardWrap = document.querySelector('.grs-wrap');
  const wizardSteps = Array.from(document.querySelectorAll('.grs-wizard-step'));
  const settingsFormClassic = document.getElementById('grs-settings-form-classic');
  const wizardPrev = document.getElementById('grs-wizard-prev');
  const wizardNext = document.getElementById('grs-wizard-next');
  const wizardProgressBar = document.getElementById('grs-wizard-progress-bar');
  const wizardProgressText = document.getElementById('grs-wizard-progress-text');
  const step1Status = document.getElementById('grs-step1-status');
  const step2FetchStatus = document.getElementById('grs-step2-fetch-status');
  const fetchConfirmWrap = document.getElementById('grs-fetch-confirm');
  const fetchRetryBtn = document.getElementById('grs-fetch-retry');
  const fetchContinueBtn = document.getElementById('grs-fetch-continue');
  const settingsForm = document.getElementById('grs-settings-form');
  const wizardSaveBtn = document.getElementById('grs-wizard-save');
  const wizardSaveWrap = document.getElementById('grs-wizard-save-wrap');

  const googleRatingMode = document.getElementById('grs_rating_mode_default');
  const googleReviewCountMode = document.getElementById('grs_review_count_mode');
  const showSummary = document.getElementById('grs_show_summary_default');
  const autoplayDefault = document.getElementById('grs_autoplay_default');
  const cronEnabled = document.getElementById('grs_cron_enabled');

  const copyShortcodeBtn = document.getElementById('grs-copy-shortcode');
  const copyShortcodeText = document.getElementById('grs-shortcode-text');
  const copyStatus = document.getElementById('grs-copy-status');

  const applyClassicVisibility = () => {
    if (!settingsFormClassic) {
      return;
    }

    const activeTab = settingsFormClassic.dataset.activeTab || 'general';

    const summarySelect = document.getElementById('grs_show_summary_default_classic');
    const ratingModeSelect = document.getElementById('grs_rating_mode_default_classic');
    const countModeSelect = document.getElementById('grs_review_count_mode_classic');
    const autoplayCheckbox = document.getElementById('grs_autoplay_default_classic');
    const cronCheckbox = document.getElementById('grs_cron_enabled_classic');

    const toggleRow = (inputId, visible, tabName) => {
      const input = document.getElementById(inputId);
      const row = input ? input.closest('tr') : null;
      if (row) {
        row.style.display = visible && activeTab === tabName ? '' : 'none';
      }
    };

    const summaryOn = !summarySelect || summarySelect.checked;
    const manualRatingOn = summaryOn && !!ratingModeSelect && ratingModeSelect.value === 'manual';
    const customCountOn = summaryOn && !!countModeSelect && countModeSelect.value === 'custom';
    const autoplayOn = !autoplayCheckbox || autoplayCheckbox.checked;
    const cronOn = !!cronCheckbox && cronCheckbox.checked;

    toggleRow('grs_review_count_mode_classic', summaryOn, 'google');
    toggleRow('grs_custom_review_count_classic', customCountOn, 'google');
    toggleRow('grs_rating_mode_default_classic', summaryOn, 'google');
    toggleRow('grs_manual_rating_default_classic', manualRatingOn, 'google');
    toggleRow('grs_autoplay_interval_default_classic', autoplayOn, 'google');
    toggleRow('grs_cron_frequency_classic', cronOn, 'general');
    toggleRow('grs_cron_time_classic', cronOn, 'general');
    toggleRow('grs_cron_max_reviews_classic', cronOn, 'general');
  };

  if (!wizardSteps.length) {
    applyClassicVisibility();

    ['grs_show_summary_default_classic', 'grs_rating_mode_default_classic', 'grs_review_count_mode_classic', 'grs_autoplay_default_classic', 'grs_cron_enabled_classic']
      .forEach((id) => {
        const node = document.getElementById(id);
        if (node) {
          node.addEventListener('change', applyClassicVisibility);
        }
      });

    if (copyShortcodeBtn && copyShortcodeText) {
      copyShortcodeBtn.addEventListener('click', async () => {
        const shortcode = copyShortcodeText.textContent || '';
        if (!shortcode) {
          return;
        }

        try {
          await navigator.clipboard.writeText(shortcode);
          if (copyStatus) {
            copyStatus.textContent = 'Copied!';
          }
        } catch (error) {
          if (copyStatus) {
            copyStatus.textContent = 'Could not copy. Please copy manually.';
          }
          console.error(error);
        }
      });
    }

    return;
  }

  const totalSteps = wizardSteps.length;

  const storageKey = 'dsbrsl-lite-wizard-step';
  const wizardReset = wizardWrap?.dataset?.wizardReset === '1';
  let currentWizardStep = 0;
  let nextIsFetching = false;
  let stepTwoFetchSucceeded = false;

  if (wizardReset) {
    window.localStorage.removeItem(storageKey);
    currentWizardStep = 0;
  } else {
    const storedStepRaw = window.localStorage.getItem(storageKey);
    const storedStep = Number.parseInt(storedStepRaw || '0', 10);
    if (Number.isInteger(storedStep) && storedStep >= 0 && storedStep < totalSteps) {
      currentWizardStep = storedStep;
    }
  }

  const clampWizardStepToValidState = () => {
    const token = (document.getElementById('grs_token')?.value || '').trim();
    const placeUrl = (document.getElementById('grs_place_url')?.value || '').trim();

    if (!token && currentWizardStep > 0) {
      currentWizardStep = 0;
      return;
    }

    if (!placeUrl && currentWizardStep > 1) {
      currentWizardStep = 1;
    }
  };

  const setStatusState = (statusNode, state) => {
    if (!statusNode) {
      return;
    }

    statusNode.classList.remove('is-error', 'is-success', 'is-loading');
    if (state) {
      statusNode.classList.add(`is-${state}`);
    }
  };

  const showFetchActionState = (allowRetry) => {
    if (fetchConfirmWrap) {
      fetchConfirmWrap.style.display = 'flex';
    }
    if (fetchRetryBtn) {
      fetchRetryBtn.style.display = allowRetry ? 'inline-flex' : 'none';
    }
    if (fetchContinueBtn) {
      fetchContinueBtn.style.display = 'none';
    }
  };

  const validateRequiredField = (node, message, statusNode) => {
    if (!node) {
      return false;
    }

    const value = (node.value || '').trim();
    if (value) {
      if (statusNode) {
        setStatus(statusNode, '');
        setStatusState(statusNode, '');
      }
      return true;
    }

    node.setCustomValidity(message);
    node.reportValidity();
    node.setCustomValidity('');
    if (statusNode) {
      setStatus(statusNode, message, true);
      setStatusState(statusNode, 'error');
    }
    return false;
  };

  const toggleTargets = (selector, visible) => {
    const targets = Array.from(document.querySelectorAll(selector));
    targets.forEach((target) => target.classList.toggle('grs-row-hidden', !visible));
  };

  const setStatus = (statusNode, text, isError = false) => {
    if (!statusNode) {
      return;
    }
    statusNode.textContent = text || '';
    statusNode.style.color = isError ? '#b32d2e' : '#50575e';
  };

  const applyConditionalQuestions = () => {
    const summaryOn = !showSummary || showSummary.checked;
    const manualRatingOn = summaryOn && !!googleRatingMode && googleRatingMode.value === 'manual';
    const customCountOn = summaryOn && !!googleReviewCountMode && googleReviewCountMode.value === 'custom';
    const autoplayOn = !autoplayDefault || autoplayDefault.checked;
    const cronOn = !!cronEnabled && cronEnabled.checked;

    toggleTargets('.grs-summary-dependent', summaryOn);
    toggleTargets('#grs-manual-rating-wrap', manualRatingOn);
    toggleTargets('#grs-custom-review-count-wrap', customCountOn);
    toggleTargets('#grs-autoplay-interval-wrap', autoplayOn);
    toggleTargets('.grs-cron-dependent', cronOn);
  };

  const updateNextButtonLabel = () => {
    if (!wizardNext) {
      return;
    }
    if (currentWizardStep === 1) {
      wizardNext.textContent = nextIsFetching
        ? 'Fetching reviews...'
        : (stepTwoFetchSucceeded ? 'Next' : 'Fetch reviews now');
      wizardNext.disabled = nextIsFetching;
      return;
    }
    wizardNext.textContent = 'Next';
    wizardNext.disabled = false;
  };

  const renderWizard = () => {
    clampWizardStepToValidState();

    if (currentWizardStep !== 1) {
      stepTwoFetchSucceeded = false;
      if (fetchConfirmWrap) {
        fetchConfirmWrap.style.display = 'none';
      }
    }

    wizardSteps.forEach((step, index) => {
      step.classList.toggle('grs-step-active', index === currentWizardStep);
    });

    const current = currentWizardStep + 1;
    const percent = Math.round((current / totalSteps) * 100);

    if (wizardProgressBar) {
      wizardProgressBar.style.width = `${percent}%`;
    }

    if (wizardProgressText) {
      wizardProgressText.textContent = `Step ${current} of ${totalSteps}`;
    }

    if (wizardPrev) {
      wizardPrev.disabled = currentWizardStep === 0;
    }

    if (wizardNext) {
      wizardNext.style.display = currentWizardStep === totalSteps - 1 ? 'none' : 'inline-flex';
    }

    if (wizardSaveWrap) {
      wizardSaveWrap.style.display = currentWizardStep === totalSteps - 1 ? 'inline-flex' : 'none';
    } else if (wizardSaveBtn) {
      wizardSaveBtn.style.display = currentWizardStep === totalSteps - 1 ? 'inline-flex' : 'none';
    }

    updateNextButtonLabel();
    window.localStorage.setItem(storageKey, String(currentWizardStep));
  };

  const fetchReviewsFromStepTwo = async () => {
    const tokenField = document.getElementById('grs_token');
    const placeUrlField = document.getElementById('grs_place_url');
    const token = (tokenField?.value || '').trim();
    const placeUrl = (placeUrlField?.value || '').trim();

    if (!validateRequiredField(tokenField, 'Please add your Apify account token first.', step2FetchStatus)) {
      stepTwoFetchSucceeded = false;
      showFetchActionState(false);
      updateNextButtonLabel();
      return false;
    }

    if (!validateRequiredField(placeUrlField, 'Please add your Google Maps URL first.', step2FetchStatus)) {
      stepTwoFetchSucceeded = false;
      showFetchActionState(false);
      updateNextButtonLabel();
      return false;
    }

    const maxReviewsRaw = (document.getElementById('grs_max_reviews')?.value || '').trim();
    const maxReviewsPreview = maxReviewsRaw === ''
      ? 'all available reviews'
      : `up to ${maxReviewsRaw} review${maxReviewsRaw === '1' ? '' : 's'}`;

    if (typeof dsbrslAdmin === 'undefined') {
      setStatus(step2FetchStatus, 'Request config is missing. Reload page and try again.', true);
      return false;
    }

    nextIsFetching = true;
    stepTwoFetchSucceeded = false;
    updateNextButtonLabel();
    setStatus(step2FetchStatus, `Fetching ${maxReviewsPreview}... This may take a few minutes. Please do not close this page.`);
    setStatusState(step2FetchStatus, 'loading');

    try {
      const formData = new FormData();
      formData.append('action', dsbrslAdmin.action);
      formData.append('nonce', dsbrslAdmin.nonce);
      formData.append('scope', 'google');

      const draftSettings = {
        token: (document.getElementById('grs_token')?.value || '').trim(),
        place_url: (document.getElementById('grs_place_url')?.value || '').trim(),
        place_id: (document.getElementById('grs_place_id')?.value || '').trim(),
        max_reviews: (document.getElementById('grs_max_reviews')?.value || '').trim(),
        language: (document.getElementById('grs_language')?.value || '').trim(),
        google_places_api_key: (document.getElementById('grs_google_places_api_key')?.value || '').trim(),
        use_places_api_summary: document.getElementById('grs_use_places_api_summary')?.checked ? '1' : '0'
      };
      formData.append('draft_settings', JSON.stringify(draftSettings));

      const response = await fetch(dsbrslAdmin.ajaxUrl, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      });

      let payload;
      try {
        payload = await response.json();
      } catch (parseError) {
        throw new Error('Server returned an invalid response while fetching reviews.');
      }

      if (!response.ok || !payload.success) {
        setStatus(step2FetchStatus, payload?.data?.message || 'Fetch failed.', true);
        setStatusState(step2FetchStatus, 'error');
        showFetchActionState(false);
        nextIsFetching = false;
        updateNextButtonLabel();
        return false;
      }

      setStatus(step2FetchStatus, payload?.data?.message || 'Reviews fetched successfully.');
      setStatusState(step2FetchStatus, 'success');
      stepTwoFetchSucceeded = true;
      showFetchActionState(false);
      nextIsFetching = false;
      updateNextButtonLabel();
      return true;
    } catch (error) {
      setStatus(step2FetchStatus, error?.message || 'Request failed. Check server logs / console.', true);
      setStatusState(step2FetchStatus, 'error');
      stepTwoFetchSucceeded = false;
      showFetchActionState(false);
      nextIsFetching = false;
      updateNextButtonLabel();
      console.error(error);
      return false;
    }
  };

  if (wizardPrev) {
    wizardPrev.addEventListener('click', () => {
      if (currentWizardStep <= 0 || nextIsFetching) {
        return;
      }
      currentWizardStep -= 1;
      renderWizard();
    });
  }

  if (wizardNext) {
    wizardNext.addEventListener('click', async () => {
      if (nextIsFetching || currentWizardStep >= totalSteps - 1) {
        return;
      }

      if (currentWizardStep === 0) {
        const tokenField = document.getElementById('grs_token');
        if (!validateRequiredField(tokenField, 'Please add your Apify account token to continue.', step1Status)) {
          return;
        }
      }

      if (currentWizardStep === 1) {
        if (stepTwoFetchSucceeded) {
          currentWizardStep += 1;
          renderWizard();
          return;
        }
        await fetchReviewsFromStepTwo();
        return;
      }

      currentWizardStep += 1;
      renderWizard();
    });
  }

  if (fetchRetryBtn) {
    fetchRetryBtn.addEventListener('click', async () => {
      await fetchReviewsFromStepTwo();
    });
  }

  if (showSummary) {
    showSummary.addEventListener('change', applyConditionalQuestions);
  }
  if (googleRatingMode) {
    googleRatingMode.addEventListener('change', applyConditionalQuestions);
  }
  if (googleReviewCountMode) {
    googleReviewCountMode.addEventListener('change', applyConditionalQuestions);
  }
  if (autoplayDefault) {
    autoplayDefault.addEventListener('change', applyConditionalQuestions);
  }
  if (cronEnabled) {
    cronEnabled.addEventListener('change', applyConditionalQuestions);
  }

  if (settingsForm) {
    settingsForm.addEventListener('submit', () => {
      window.localStorage.removeItem(storageKey);
    });
  }

  if (copyShortcodeBtn && copyShortcodeText) {
    copyShortcodeBtn.addEventListener('click', async () => {
      const shortcode = copyShortcodeText.textContent || '';
      if (!shortcode) {
        return;
      }

      try {
        await navigator.clipboard.writeText(shortcode);
        if (copyStatus) {
          copyStatus.textContent = 'Copied!';
        }
      } catch (error) {
        if (copyStatus) {
          copyStatus.textContent = 'Could not copy. Please copy manually.';
        }
        // eslint-disable-next-line no-console
        console.error(error);
      }
    });
  }

  applyConditionalQuestions();
  renderWizard();
})();
