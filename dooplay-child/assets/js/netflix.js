jQuery(document).ready(function($) {

    

    // 1. OPTIMIZED HEADER SCROLL (RequestAnimationFrame)
    const nav = document.getElementById('header');
    let ticking = false;

    if (nav) {
        window.addEventListener('scroll', () => {
            if (!ticking) {
                window.requestAnimationFrame(() => {
                    nav.classList.toggle('nav_black', window.scrollY >= 50);
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });
    }

    // 2. SLIDER LOGIC & ARROWS
    const rows = document.querySelectorAll('.row');

    rows.forEach(row => {
        const slider = row.querySelector('.row__sliders');
        if (!slider) return;

        // Create Arrows
        const leftArrow = document.createElement('button');
        leftArrow.className = 'slider-arrow arrow-left hidden'; // Start hidden
        leftArrow.innerHTML = '<i class="fas fa-chevron-left"></i>';
        
        const rightArrow = document.createElement('button');
        rightArrow.className = 'slider-arrow arrow-right';
        rightArrow.innerHTML = '<i class="fas fa-chevron-right"></i>';

        row.appendChild(leftArrow);
        row.appendChild(rightArrow);

        // Scroll Amount (Responsive)
        const getScrollAmount = () => {
            return window.innerWidth * 0.8; // Scroll 80% of screen width
        };

        // Scroll Handler (Updates Arrow Visibility)
        const updateArrows = () => {
            const maxScrollLeft = slider.scrollWidth - slider.clientWidth - 10;
            
            if (slider.scrollLeft > 10) leftArrow.classList.remove('hidden');
            else leftArrow.classList.add('hidden');

            if (slider.scrollLeft >= maxScrollLeft) rightArrow.classList.add('hidden');
            else rightArrow.classList.remove('hidden');
        };

        slider.addEventListener('scroll', () => {
            if(!ticking) {
                window.requestAnimationFrame(() => {
                    updateArrows();
                    ticking = false;
                });
                ticking = true;
            }
        }, { passive: true });

        // Click Events
        leftArrow.addEventListener('click', () => {
            slider.scrollBy({ left: -getScrollAmount(), behavior: 'smooth' });
        });

        rightArrow.addEventListener('click', () => {
            slider.scrollBy({ left: getScrollAmount(), behavior: 'smooth' });
        });
    });

    // 3. HOVER DELAY (Desktop Only - Performance Optimized)
    const isTouchDevice = 'ontouchstart' in document.documentElement;
    
    if (!isTouchDevice && window.innerWidth > 1024) {
        let hoverTimeout;
        let currentItem = null;

        $(document).on('mouseenter', '.row__item', function() {
            const $this = $(this);
            currentItem = $this;
            if(hoverTimeout) clearTimeout(hoverTimeout);
            hoverTimeout = setTimeout(() => {
                if(currentItem && currentItem.is($this)) {
                    $('.row__item').removeClass('hover-active');
                    $this.addClass('hover-active');
                }
            }, 400); 
        });

        $(document).on('mouseleave', '.row__item', function() {
            const $this = $(this);
            currentItem = null;
            clearTimeout(hoverTimeout);
            setTimeout(() => {
                if(!$this.is(':hover')) {
                    $this.removeClass('hover-active');
                }
            }, 50);
        });
    }

    // 4. LAZY LOAD
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if(img.dataset.src) {
                        img.src = img.dataset.src;
                        img.onload = () => img.classList.add('loaded'); 
                        img.classList.remove('lazy');
                    }
                    observer.unobserve(img);
                }
            });
        }, { rootMargin: "500px 0px" }); 

        document.querySelectorAll('img.lazy').forEach(img => imageObserver.observe(img));
        
        const bgObserver = new IntersectionObserver((entries, obs) => {
            entries.forEach(entry => {
                if(entry.isIntersecting) {
                    const el = entry.target;
                    if(el.dataset.bg) el.style.backgroundImage = `url('${el.dataset.bg}')`;
                    obs.unobserve(el);
                }
            });
        });
        document.querySelectorAll('.lazy-bg').forEach(el => bgObserver.observe(el));
    }

    /* 6. BILLBOARD AUTO-FADE SLIDER */
    const billboardSlides = document.querySelectorAll('.banner-slide');
    
    if (billboardSlides.length > 1) {
        let currentSlide = 0;
        const slideInterval = 7000; // 7 Seconds per slide

        const nextSlide = () => {
            // Remove active from current
            billboardSlides[currentSlide].classList.remove('active');
            
            // Move to next
            currentSlide = (currentSlide + 1) % billboardSlides.length;
            
            // Add active to next
            billboardSlides[currentSlide].classList.add('active');
        };

        let sliderTimer = setInterval(nextSlide, slideInterval);

        // Optional: Pause on hover (Desktop only)
        const sliderContainer = document.getElementById('nfx-billboard-slider');
        if (sliderContainer) {
            sliderContainer.addEventListener('mouseenter', () => clearInterval(sliderTimer));
            sliderContainer.addEventListener('mouseleave', () => {
                sliderTimer = setInterval(nextSlide, slideInterval);
            });
        }
    }
});