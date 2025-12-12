(function(){
    function $qa(sel, ctx){ return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
    function $q(sel, ctx){ return (ctx || document).querySelector(sel); }

    var tabs = $qa('.netflinks-tab');
    var skeleton = $q('#netflinks-skeleton');
    var grids = $qa('.netflinks-grid');

    // Access the path passed from WordPress wp_localize_script
    var svgPath = (typeof nfLinksData !== 'undefined') ? nfLinksData.svg_path : '';

    function revealAfterDelay() {
        if (!skeleton) return;
        skeleton.style.display = 'grid';
        grids.forEach(function(g){ g.style.opacity = '0'; });
        setTimeout(function(){
            skeleton.style.display = 'none';
            grids.forEach(function(g){
                g.style.opacity = '1';
                g.style.transition = 'opacity .28s ease';
            });
        }, 320);
    }
    revealAfterDelay();

    tabs.forEach(function(tab){
        tab.addEventListener('click', function(){
            var mode = tab.getAttribute('data-mode');
            tabs.forEach(function(t){ t.classList.remove('active'); t.setAttribute('aria-selected','false'); });
            tab.classList.add('active'); tab.setAttribute('aria-selected','true');

            grids.forEach(function(g){
                if (g.getAttribute('data-mode') === mode) {
                    if (g.children.length === 0) {
                        try {
                            var rows = JSON.parse(g.getAttribute('data-rows') || '[]');
                            rows.forEach(function(r){
                                var art = document.createElement('article');
                                art.className = 'netflinks-card';
                                var html = '';
                                
                                // --- JS QUALITY PILL LOGIC ---
                                var q = (r.quality||'').toLowerCase();
                                var imgTag = '<img src="' + svgPath + '480p.svg" alt="SD">'; // Default

                                // Check for '4k', '2160', or 'uhd'
                                if (q.indexOf('4k') !== -1 || q.indexOf('2160') !== -1 || q.indexOf('uhd') !== -1) {
                                    imgTag = '<img src="' + svgPath + '4K.svg" alt="4K">';
                                } else if (q.indexOf('1080') !== -1) {
                                    imgTag = '<img src="' + svgPath + '1080p.svg" alt="1080p">';
                                } else if (q.indexOf('720') !== -1) {
                                    imgTag = '<img src="' + svgPath + '720p.svg" alt="720p">';
                                } else if (q.indexOf('480') !== -1) {
                                    imgTag = '<img src="' + svgPath + '480p.svg" alt="480p">';
                                }

                                html += '<div class="netflinks-toprow">';
                                html += '<div class="netflinks-quality-pill">' + imgTag + '<span>' + (r.quality||'') + '</span></div>';
                                if (r.size) html += '<div class="netflinks-size-pill">📦 '+(r.size||'')+'</div>';
                                html += '</div>';
                                // -----------------------------

                                html += '<div style="height:8px;"></div>';
                                html += '<div class="netflinks-server">';
                                if (r.server_icon) html += '<div style="display:flex;align-items:center;gap:12px;flex:1;"><img src="'+r.server_icon+'" style="width:36px;height:36px;border-radius:50%;object-fit:cover;"><div style="font-size:1rem;font-weight:700;color:#fff;">'+(r.pretty_server||r.server_raw||'Unknown')+'</div></div>';
                                else html += '<div style="display:flex;align-items:center;gap:12px;flex:1;"><svg width="36" height="36"><use xlink:href="#nf-server"></use></svg><div style="font-size:1rem;font-weight:700;color:#fff;">'+(r.pretty_server||r.server_raw||'Unknown')+'</div></div>';
                                if (r.flag || r.lang) html += '<div style="margin-left:12px;"><span class="netflinks-lang-pill">'+(r.flag?r.flag+' ':'')+(r.lang||'')+'</span></div>';
                                html += '</div>';

                                html += '<div class="netflinks-actions"><a class="download-btn" href="'+(r.url||'#')+'" target="_blank" rel="noopener noreferrer"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12"></path><path d="M6 11l6 6 6-6"></path><path d="M5 21h14"></path></svg><span>'+(r.label||'Download')+'</span></a></div>';

                                art.innerHTML = html;
                                g.appendChild(art);
                            });
                        } catch(e){}
                    }
                    g.style.display = 'grid';
                    g.style.opacity = '1';
                } else {
                    g.style.display = 'none';
                }
            });
        });
    });
})();