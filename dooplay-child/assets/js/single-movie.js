/* ======================================================
   SINGLE MOVIE LOGIC (Final)
   ====================================================== */

document.addEventListener("DOMContentLoaded", () => {

    const tabs = document.querySelectorAll("#player-tabs .tab");
    const container = document.getElementById("playerContent");
    const fakePlayer = document.getElementById('fakeplayer');
    const fakeClick  = document.getElementById('clickfakeplayer');

    // 1. Player Source Switcher
    function loadSource(index) {
        if(!playerSources[index]) return;
        const data = playerSources[index];
        container.innerHTML = '';
        if (data.type === 'html') {
            container.innerHTML = data.content;
        } else {
            const iframe = document.createElement('iframe');
            iframe.src = data.content;
            iframe.setAttribute('allow', 'autoplay; fullscreen; encrypted-media');
            iframe.setAttribute('allowfullscreen', '');
            iframe.style.width = "100%";
            iframe.style.height = "100%";
            iframe.style.border = "none";
            container.appendChild(iframe);
        }
    }

    // 2. Fake Player Logic
    if(fakePlayer && fakeClick) {
        fakeClick.addEventListener('click', () => {
            fakePlayer.style.display = 'none';
            loadSource(0); 
        });
    } else {
        if(typeof playerSources !== 'undefined' && playerSources.length > 0) loadSource(0);
    }

    // 3. Tab Click Logic
    if(tabs.length > 0 && typeof playerSources !== 'undefined' && playerSources.length > 0){
        tabs[0].classList.add("selected");
        if(!fakePlayer) loadSource(0); 
    }

    tabs.forEach(tab => {
        tab.addEventListener("click", () => {
            if(fakePlayer) fakePlayer.style.display = 'none';
            tabs.forEach(t => t.classList.remove("selected"));
            tab.classList.add("selected");
            loadSource(tab.dataset.index);
        });
    });

    // 4. Lazy Load Images
    const lazyImageObserver = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src; 
                img.onload = () => {
                    img.classList.remove("skeleton");
                    img.classList.add("is-visible");
                };
                observer.unobserve(img);
            }
        });
    });

    const heroImages = document.querySelectorAll('.lazy-load-hero');
    heroImages.forEach(img => lazyImageObserver.observe(img));

    const castImages = document.querySelectorAll('#dt-cast .person .img img, #dt-director .person .img img');
    castImages.forEach(img => {
        img.classList.add('skeleton', 'lazy-loaded-img');
        if(img.complete) {
            img.classList.remove('skeleton');
            img.classList.add('is-visible');
        } else {
            img.addEventListener('load', () => {
                img.classList.remove('skeleton');
                img.classList.add('is-visible');
            });
        }
    });

    // 5. Trailer Modal
    const heroTrailerBtn = document.getElementById("openTrailerHero");
    const closeTrailerBtn = document.getElementById("closeTrailer");
    const modal = document.getElementById("trailerModal");
    const trailerFrame = document.getElementById("trailerFrame");

    if(heroTrailerBtn){
        heroTrailerBtn.addEventListener("click", (e) => {
            e.preventDefault();
            modal.classList.add("active");
            if(trailerFrame) trailerFrame.src = trailerFrame.dataset.src + "?autoplay=1";
        });
    }

    if(closeTrailerBtn){
        closeTrailerBtn.addEventListener("click", () => {
            modal.classList.remove("active");
            if(trailerFrame) trailerFrame.src = ""; 
        });
    }
    
    window.addEventListener("click", (e) => {
        if (e.target == modal) {
            modal.classList.remove("active");
            if(trailerFrame) trailerFrame.src = "";
        }
    });

});

// 6. 3D Tilt Effect (Updated to target .heroc-poster)
const tiltElements = document.querySelectorAll('.heroc-poster, #dt-cast .person, #dt-director .person');
if (window.matchMedia("(hover: hover)").matches) {
    tiltElements.forEach(card => {
        card.addEventListener('mousemove', (e) => {
            const rect = card.getBoundingClientRect();
            const x = e.clientX - rect.left; 
            const y = e.clientY - rect.top;  
            const centerX = rect.width / 2;
            const centerY = rect.height / 2;
            const rotateX = ((y - centerY) / centerY) * -15; 
            const rotateY = ((x - centerX) / centerX) * 15;
            card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) scale3d(1.05, 1.05, 1.05)`;
        });
        card.addEventListener('mouseleave', () => {
            card.style.transform = `perspective(1000px) rotateX(0deg) rotateY(0deg) scale3d(1, 1, 1)`;
        });
    });
}

// 7. Parallax Effect (Updated to target .heroc)
window.addEventListener("scroll", function() {
    const hero = document.querySelector(".heroc"); // Changed from .hero to .heroc
    if (!hero) return;
    const limit = hero.offsetHeight;
    const scrolled = window.pageYOffset;
    if (scrolled <= limit) hero.style.backgroundPositionY = (scrolled * 0.5) + "px";
});