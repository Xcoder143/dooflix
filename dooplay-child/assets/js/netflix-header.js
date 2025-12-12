document.addEventListener("DOMContentLoaded", () => {

    /* ======================================================
       1. HEADER SCROLL EFFECT (Turn black on scroll)
    ====================================================== */
    const header = document.getElementById("nfx-header");
    const onScroll = () => {
        if (header) {
            if (window.scrollY > 60) header.classList.add("nav_black");
            else header.classList.remove("nav_black");
        }
    };
    onScroll();
    window.addEventListener("scroll", onScroll);


    /* ======================================================
       2. SEARCH TOGGLE & ICON ANIMATION
    ====================================================== */
    const nfxSearchBtn = document.getElementById("nfxSearchBtn");
    const nfxSearchPanel = document.getElementById("nfxSearchPanel");
    const searchInput = document.getElementById("nfxSearchInput");

    const toggleSearchIcon = (isOpen) => {
        const icon = nfxSearchBtn.querySelector("i");
        if (icon) {
            if (isOpen) {
                icon.classList.remove("fa-search");
                icon.classList.add("fa-times");
            } else {
                icon.classList.remove("fa-times");
                icon.classList.add("fa-search");
            }
        }
    };

    if (nfxSearchBtn && nfxSearchPanel) {
        nfxSearchBtn.addEventListener("click", (e) => {
            e.stopPropagation();
            const isOpen = nfxSearchPanel.classList.toggle("open");
            toggleSearchIcon(isOpen);
            
            if (isOpen && searchInput) setTimeout(() => searchInput.focus(), 100);
        });

        document.addEventListener("click", (e) => {
            if (!nfxSearchPanel.contains(e.target) && e.target !== nfxSearchBtn) {
                nfxSearchPanel.classList.remove("open");
                toggleSearchIcon(false);
            }
        });
    }


    /* ======================================================
       3. LIVE SEARCH & GHOST CARD LOGIC
    ====================================================== */
    const resultsBox = document.getElementById("nfxSearchResults");
    let searchTimeout = null;

    // Helper: Kill any active ghost cards
    const killGhost = () => {
        const existing = document.querySelector('.nfx-ghost-card');
        if (existing) existing.remove();
    };

    const createGhostCard = (originalItem) => {
        killGhost(); // Clear old ones

        const rect = originalItem.getBoundingClientRect();
        const clone = originalItem.cloneNode(true);
        
        clone.classList.add('nfx-ghost-card');
        clone.style.position = 'fixed';
        clone.style.top = rect.top + 'px';
        clone.style.left = rect.left + 'px';
        clone.style.width = rect.width + 'px';
        clone.style.height = rect.height + 'px';
        clone.style.zIndex = '999999';
        clone.style.margin = '0';
        
        document.body.appendChild(clone);

        requestAnimationFrame(() => {
            clone.classList.add('popped');
        });

        clone.addEventListener('mouseleave', () => {
            clone.classList.remove('popped');
            setTimeout(() => clone.remove(), 200);
        });
        
        clone.addEventListener('click', (e) => {
            e.preventDefault();
            window.location.href = originalItem.href;
        });

        clone.addEventListener('wheel', (e) => {
            killGhost();
        });
    };

    if (searchInput && resultsBox) {
        resultsBox.addEventListener('scroll', killGhost);

        searchInput.addEventListener("input", function () {
            const q = this.value.trim();
            clearTimeout(searchTimeout);

            if (q.length < 2) {
                resultsBox.classList.remove("active");
                resultsBox.innerHTML = "";
                return;
            }

            resultsBox.innerHTML = `<div class="nfx-search-loading">Searching…</div>`;
            resultsBox.classList.add("active");

            searchTimeout = setTimeout(() => {
                // Ensure nfxHeaderData exists before using it
                if (typeof nfxHeaderData === 'undefined') {
                    console.error("nfxHeaderData is missing. Localize script in functions.php");
                    return;
                }

                const form = new FormData();
                form.append("action", "nfx_search");
                form.append("q", q);
                form.append("nonce", nfxHeaderData.nonce);

                fetch(nfxHeaderData.ajax_url, {
                    method: "POST",
                    body: form,
                })
                .then((res) => res.json())
                .then((data) => {
                    if (!data || !data.length) {
                        resultsBox.innerHTML = `<div class="nfx-search-loading">No results found</div>`;
                        return;
                    }

                    let listHTML = data.map((item, index) => {
                        let badgeHTML = '';
                        if (item.badge === '4K') badgeHTML += `<span class="nfx-search-badge nfx-badge-4k">4K</span>`;
                        else if (item.badge === 'HD') badgeHTML += `<span class="nfx-search-badge nfx-badge-1080p">HD</span>`;
                        else if (item.badge === '720p') badgeHTML += `<span class="nfx-search-badge nfx-badge-720p">720p</span>`;
                        
                        if (item.isHDR) badgeHTML += `<span class="nfx-search-badge nfx-badge-hdr">HDR</span>`;

                        return `
                        <a class="nfx-search-item ${index === 0 ? 'top' : ''}" href="${item.url}">
                            <img src="${item.img}" alt="${item.title}">
                            <div class="nfx-search-info">
                                <div class="nfx-search-title">${item.title}</div>
                                <div class="nfx-search-sub">
                                    ${item.year ? item.year : ''} ${item.year ? '•' : ''} ${item.type}
                                    ${badgeHTML}
                                </div>
                            </div>
                        </a>`;
                    }).join('');

                    listHTML += `
                    <a href="${nfxHeaderData.home_url}?s=${encodeURIComponent(q)}" class="nfx-show-all-btn">
                        Show all results for "${q}" <i class="fas fa-chevron-right"></i>
                    </a>`;

                    resultsBox.innerHTML = listHTML;

                    const items = resultsBox.querySelectorAll('.nfx-search-item');
                    items.forEach(item => {
                        item.addEventListener('mouseenter', () => createGhostCard(item));
                    });
                })
                .catch(() => {
                    resultsBox.innerHTML = `<div class="nfx-search-loading">Error loading results</div>`;
                });
            }, 250);
        });
    }

    // Window scroll also kills ghost
    window.addEventListener('scroll', killGhost);


    /* ======================================================
       4. PROFILE DROPDOWN LOGIC (ADDED FIX)
    ====================================================== */
    const profileToggle = document.getElementById('nfxProfileToggle');
    const profileMenu = document.getElementById('nfxProfileMenu');

    if (profileToggle && profileMenu) {
        profileToggle.addEventListener('click', function(e) {
            e.stopPropagation(); 
            profileMenu.classList.toggle('open');
        });

        // Close when clicking outside
        document.addEventListener('click', function(e) {
            if (!profileToggle.contains(e.target) && !profileMenu.contains(e.target)) {
                profileMenu.classList.remove('open');
            }
        });
    }


    /* ======================================================
       5. MOBILE MENU LOGIC (ADDED FIX)
    ====================================================== */
    const mobileOpenBtn = document.getElementById('nfxMobileOpen');
    const mobileCloseBtn = document.getElementById('nfxMobileClose');
    const mobileMenu = document.getElementById('nfxMobileMenu');

    if (mobileOpenBtn && mobileMenu) {
        mobileOpenBtn.addEventListener('click', function() {
            mobileMenu.classList.add('open');
        });
    }

    if (mobileCloseBtn && mobileMenu) {
        mobileCloseBtn.addEventListener('click', function() {
            mobileMenu.classList.remove('open');
        });
    }

});