
function changeScenery(scenery, buttonElement) {
    const nav = qs('nav');
    const mainContent = qs('main');
    const mainContentLoader = qs('.mainContentLoader.loaderOverlay');
    const header = qs('header');
    if (!nav || !mainContent) return;
    nav.querySelector('button.active')?.classList.remove('active');
    header.style.position = 'relative';
    
    buttonElement.classList.add('active');
    switch (scenery) {
        case 'home':
            mainContentLoader.style.opacity = '1';
            location.reload();
            break;
        case 'projects':
            mainContentLoader.style.opacity = '1';
            if (!document.querySelector('script[src="js/projects.js"]')) {
                const script = document.createElement('script');
                script.src = 'js/projects.js';
                document.head.appendChild(script);
            }
            fetch('pages/projectsLibrary.php')
                .then(response => response.text())
                .then(html => {
                    mainContent.innerHTML = html;
                    mainContent.classList.add('projects');
                    setTimeout(() => {
                        goToTopButton.classList.remove('visible');
                    }, 10);
                    waitForImages(mainContent).then(() => {
                        mainContentLoader.style.opacity = '0';
                    });
                })
                .catch(err => console.error('Błąd ładowania projektów:', err));
            break;
        case 'whatsnew':
            fetch('pages/updates.php')
                .then(response => response.text())
                .then(html => {
                    mainContent.innerHTML = html;
                    setTimeout(() => {
                        goToTopButton.classList.remove('visible');
                    }, 10);
                })
                .catch(err => console.error('Błąd ładowania:', err));
            break;
        case 'plans':
            fetch('pages/plans.php')
                .then(response => response.text())
                .then(html => {
                    mainContent.innerHTML = html;
                    setTimeout(() => {
                        goToTopButton.classList.remove('visible');
                    }, 10);
                })
                .catch(err => console.error('Błąd ładowania:', err));
            break;
    }
}


function carouselChange(id, clicked) {
    const showcasedProject = qs('#showcase' + id);
    const buttonElement = qs('#carouselButton' + id);
    const carouselSlider = qs('#carouselSlider');
    if (!carouselSlider) return;

    carouselSlider.querySelector('button.active')?.classList.remove('active');
    qsa('.showcasedProject').forEach(proj => {
        proj.style.zIndex = '0';
        proj.classList.remove('active');
    });

    buttonElement.classList.add('active');
    showcasedProject.style.zIndex = '10';
    showcasedProject.classList.add('active');

    if(clicked) {
        clearInterval(carouselInterval);
        startCarousel(parseInt(id) + 1);
    }
    
}

let carouselInterval = null;

function startCarousel() {
    let currentIndex = 0;
    const showcasedProjects = qsa('.showcasedProject');
    const showcasedProjectsNumber = showcasedProjects.length;
    if (showcasedProjectsNumber === 0) return;
    carouselInterval = setInterval(() => {
        currentIndex++;
        if (currentIndex >= showcasedProjectsNumber) {
            currentIndex = 0;
        }
        carouselChange(currentIndex, false);
    }, 5000);
}

const welcomeBlock = qs('#welcomeBlock');
const goToTopButton = qs('#goToTop');

function generateCircles() {
    const welcomeBlock = document.querySelector('#welcomeBlock');
    for (let i = 0; i < 7; i++) {
        welcomeBlock.innerHTML += '<div class="circle"></div>';
    }
}


window.addEventListener('load', () => {
    const wholePageLoader = qs('.wholePageLoader');
    wholePageLoader.style.opacity = '0';
    startCarousel();
    carouselChange(0, false);
    generateCircles();
    createScrollObserver({
        watchElement: qs('header'),
        onVisible: () => {
        goToTopButton.classList.remove('visible');
        },
        onHidden: () => {
        goToTopButton.classList.add('visible');
        },
        threshold: 0.75
    });
    createScrollObserver({
        watchElement: welcomeBlock,
        onVisible: () => {
        goToTopButton.classList.remove('visible');
        },
        onHidden: () => {
        goToTopButton.classList.add('visible');
        },
        threshold: 0.75
    });
    createScrollObserver({
        watchElement: qs('.welcomeBlock .stats'),
        onVisible: () => {
            qsa('.stats label[data-target]').forEach(label => {
            animateNumber(label, parseInt(label.dataset.target), 1000);
            });
        },
        threshold: 0.3
    });
});
