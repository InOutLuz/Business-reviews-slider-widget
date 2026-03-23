(function () {
  const sliders = document.querySelectorAll('[data-grs-slider]');
  if (!sliders.length) {
    return;
  }

  const clamp = (n, min, max) => Math.max(min, Math.min(max, n));

  sliders.forEach((root) => {
    const track = root.querySelector('[data-grs-track]');
    const viewport = root.querySelector('.grs-slider');
    const prevBtn = root.querySelector('[data-grs-prev]');
    const nextBtn = root.querySelector('[data-grs-next]');
    const dotsWrap = root.querySelector('[data-grs-dots]');

    if (!track || !viewport || !prevBtn || !nextBtn || !dotsWrap) {
      return;
    }

    const sourceSlides = Array.from(track.children).map((node) => node.cloneNode(true));
    const sourceCount = () => sourceSlides.length;
    const slides = () => Array.from(track.children);
    const loopEnabled = root.getAttribute('data-loop') === '1';
    const dotsEnabled = root.getAttribute('data-show-dots') === '1';
    const swipeEnabled = root.getAttribute('data-swipe') === '1';
    const colsMobile = clamp(Number.parseInt(root.getAttribute('data-mobile') || '1', 10) || 1, 1, 6);
    const colsTablet = clamp(Number.parseInt(root.getAttribute('data-tablet') || '2', 10) || 2, 1, 6);
    const colsDesktop = clamp(Number.parseInt(root.getAttribute('data-desktop') || '3', 10) || 3, 1, 6);

    let page = 0;
    let perView = 1;
    let timer = null;
    let touchStartX = 0;
    let touchDiff = 0;
    let totalPages = 0;
    let progressTrack = null;
    let progressFill = null;

    const getMetrics = () => {
      const firstSlide = slides()[0];
      const style = window.getComputedStyle(track);
      const gap = Number.parseFloat(style.columnGap || style.gap || '0') || 0;
      const viewportWidth = viewport.getBoundingClientRect().width || root.getBoundingClientRect().width;
      const deviceWidth = window.innerWidth || document.documentElement.clientWidth || viewportWidth;
      const baseSlots = deviceWidth >= 1040 ? colsDesktop : (deviceWidth >= 760 ? colsTablet : colsMobile);
      const resolvedPerView = baseSlots;

      root.style.setProperty('--grs-cols', String(Math.max(1, resolvedPerView)));

      if (!firstSlide) {
        return {
          gap,
          slideWidth: 0,
          perView: resolvedPerView,
          viewportWidth
        };
      }

      return {
        gap,
        slideWidth: firstSlide.getBoundingClientRect().width,
        perView: resolvedPerView,
        viewportWidth
      };
    };

    const maxPage = () => Math.max(0, Math.ceil(sourceCount() / perView) - 1);

    const rebuildTrack = () => {
      track.innerHTML = '';

      if (!sourceSlides.length) {
        return;
      }

      sourceSlides.forEach((node) => {
        track.appendChild(node.cloneNode(true));
      });

      if (!loopEnabled || perView <= 1 || sourceCount() <= perView) {
        return;
      }

      const remainder = sourceCount() % perView;
      const fillers = remainder === 0 ? 0 : perView - remainder;

      for (let i = 0; i < fillers; i += 1) {
        const clone = sourceSlides[i % sourceCount()].cloneNode(true);
        clone.setAttribute('aria-hidden', 'true');
        track.appendChild(clone);
      }
    };

    const setControls = () => {
      const mp = maxPage();
      if (mp <= 0) {
        prevBtn.disabled = true;
        nextBtn.disabled = true;
        return;
      }

      if (loopEnabled) {
        prevBtn.disabled = false;
        nextBtn.disabled = false;
        return;
      }

      prevBtn.disabled = page <= 0;
      nextBtn.disabled = page >= mp;
    };

    const ensureProgressMarkup = () => {
      if (progressTrack && progressFill) {
        return;
      }

      dotsWrap.innerHTML = '';

      progressTrack = document.createElement('div');
      progressTrack.className = 'grs-progress-track';

      progressFill = document.createElement('span');
      progressFill.className = 'grs-progress-fill';
      progressTrack.appendChild(progressFill);

      dotsWrap.appendChild(progressTrack);
    };

    const renderProgress = () => {
      if (!dotsEnabled || totalPages <= 1) {
        dotsWrap.style.display = 'none';
        dotsWrap.innerHTML = '';
        progressTrack = null;
        progressFill = null;
        return;
      }

      dotsWrap.style.display = 'block';
      ensureProgressMarkup();

      const clampedPage = clamp(page, 0, totalPages - 1);
      const ratio = totalPages <= 1 ? 1 : ((clampedPage + 1) / totalPages);
      progressFill.style.transform = `scaleX(${ratio})`;

      dotsWrap.setAttribute('role', 'progressbar');
      dotsWrap.setAttribute('aria-valuemin', '1');
      dotsWrap.setAttribute('aria-valuemax', String(totalPages));
      dotsWrap.setAttribute('aria-valuenow', String(clampedPage + 1));
      dotsWrap.setAttribute('aria-label', `Slide ${clampedPage + 1} of ${totalPages}`);
    };

    const go = (targetPage, instant = false) => {
      const metrics = getMetrics();
      perView = metrics.perView;
      page = clamp(targetPage, 0, maxPage());

      const firstSlide = slides()[0];
      if (!firstSlide) {
        track.style.transform = 'translateX(0px)';
        setControls();
        return;
      }

      const firstVisibleIndex = page * perView;
      const step = metrics.slideWidth + metrics.gap;
      const offset = -(firstVisibleIndex * step);

      if (instant) {
        track.style.transition = 'none';
      }
      track.style.transform = `translateX(${offset}px)`;
      if (instant) {
        window.requestAnimationFrame(() => {
          track.style.transition = '';
        });
      }

      setControls();
      renderProgress();
    };

    const buildDots = () => {
      if (!dotsEnabled) {
        dotsWrap.style.display = 'none';
        dotsWrap.innerHTML = '';
        return;
      }

      perView = getMetrics().perView;
      rebuildTrack();
      page = clamp(page, 0, maxPage());
      totalPages = maxPage() + 1;
      renderProgress();
    };

    const autoplayEnabled = root.getAttribute('data-autoplay') === '1';
    const resolveIntervalMs = () => {
      const rawInterval = root.getAttribute('data-interval')
        || root.getAttribute('data-autoplay-interval')
        || root.getAttribute('data-autoplay-interval-ms')
        || '5500';
      const parsed = Number.parseFloat(rawInterval);

      if (!Number.isFinite(parsed) || parsed <= 0) {
        return 5500;
      }

      return clamp(Math.round(parsed), 1500, 20000);
    };

    const stopAutoplay = () => {
      if (timer) {
        window.clearInterval(timer);
      }
      timer = null;
    };

    const restartAutoplay = () => {
      stopAutoplay();
      if (!autoplayEnabled || maxPage() <= 0) {
        return;
      }
      const safeInterval = resolveIntervalMs();

      timer = window.setInterval(() => {
        const mp = maxPage();
        if (loopEnabled) {
          const next = page >= mp ? 0 : page + 1;
          go(next);
          return;
        }

        if (page >= mp) {
          stopAutoplay();
          return;
        }

        go(page + 1);
      }, safeInterval);
    };

    prevBtn.addEventListener('click', () => {
      const mp = maxPage();
      if (mp <= 0) return;
      if (loopEnabled) {
        go(page <= 0 ? mp : page - 1);
      } else {
        go(page - 1);
      }
      restartAutoplay();
    });

    nextBtn.addEventListener('click', () => {
      const mp = maxPage();
      if (mp <= 0) return;
      if (loopEnabled) {
        go(page >= mp ? 0 : page + 1);
      } else {
        go(page + 1);
      }
      restartAutoplay();
    });

    if (swipeEnabled) {
      root.addEventListener('touchstart', (event) => {
        if (!event.touches || !event.touches.length) return;
        touchStartX = event.touches[0].clientX;
        touchDiff = 0;
        stopAutoplay();
      }, { passive: true });

      root.addEventListener('touchmove', (event) => {
        if (!event.touches || !event.touches.length) return;
        touchDiff = event.touches[0].clientX - touchStartX;
      }, { passive: true });

      root.addEventListener('touchend', () => {
        const threshold = 45;
        const mp = maxPage();
        if (touchDiff <= -threshold) {
          if (mp > 0) {
            if (loopEnabled) {
              go(page >= mp ? 0 : page + 1);
            } else {
              go(page + 1);
            }
          }
        } else if (touchDiff >= threshold) {
          if (mp > 0) {
            if (loopEnabled) {
              go(page <= 0 ? mp : page - 1);
            } else {
              go(page - 1);
            }
          }
        }
        restartAutoplay();
      });
    }

    root.addEventListener('mouseenter', () => {
      stopAutoplay();
    });

    root.addEventListener('mouseleave', () => {
      restartAutoplay();
    });

    const reflow = () => {
      const nextPerView = getMetrics().perView;
      if (nextPerView !== perView) {
        perView = nextPerView;
        page = clamp(page, 0, maxPage());
        buildDots();
      }
      go(page, true);
    };

    window.addEventListener('resize', reflow);
    if ('ResizeObserver' in window) {
      const ro = new ResizeObserver(() => {
        reflow();
      });
      ro.observe(viewport);
    }

    perView = getMetrics().perView;
    buildDots();
    go(0, true);
    restartAutoplay();
  });
})();
