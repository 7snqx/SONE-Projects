
const badges = [
    { border: '#3B82F6', text: '#1E40AF', background: '#DBEAFE', name: 'Pc only' },
    { border: '#F59E0B', text: '#92400E', background: '#FEF3C7', name: 'Beta' },
    { border: '#8B5CF6', text: '#5B21B6', background: '#EDE9FE', name: 'Work in progress' }
];

function renderBadges(badgeString) {
    if (!badgeString) return '';
    return badgeString.split(',').map(index => {
        const badge = badges[parseInt(index.trim())];
        if (!badge) return '';
        return `<div class="badge" style="border-color: ${badge.border}; background-color: ${badge.background}; color: ${badge.text};">
            <div class="circle" style="background-color: ${badge.border};"></div>
            ${badge.name}
        </div>`;
    }).join('');
}

function renderProject(project) {
    const lastUpdate = project.lastUpdate || 'Brak danych';
    const releaseDate = project.releaseDate === '2025-12-29' ? 'Brak danych' : project.releaseDate;
    
    return `<article class="projectCard" onclick="window.location.href='${project.url}'">
        <img class="projectImage" src="../assets/img/${project.image}" alt="${project.title}" />
        <div class="projectContent">
            <h2 class="projectTitle">${project.title}</h2>
            <div class="tags">${renderBadges(project.badge)}</div>
            <p class="projectDescription">${project.description}</p>
            <div class="projectInfo">
                <div class="infoItem">
                    <span class="material-symbols-rounded">upgrade</span>
                    <div>
                        <p>Data ostatniej aktualizacji</p>
                        <p>${lastUpdate}</p>
                    </div>
                </div>
                <div class="infoItem">
                    <div>
                        <p>Data opublikowania</p>
                        <p>${releaseDate}</p>
                    </div>
                    <span class="material-symbols-rounded">event</span>
                </div>
            </div>
        </div>
    </article>`;
}

function showX() {
    const input = qs('#projectSearchInput');
    if (qs('#clearSearchButton')) return;
    if (!input.value) return;
    
    const xButton = document.createElement('span');
    xButton.className = 'material-symbols-rounded clearSearchButton';
    xButton.id = 'clearSearchButton';
    xButton.onclick = clearSearch;
    xButton.textContent = 'close';
    input.parentElement.appendChild(xButton);
}

function clearSearch() {
    const input = qs('#projectSearchInput');
    const xButton = qs('#clearSearchButton');
    input.value = '';
    if (xButton) xButton.remove();
    searchProjects();
}

function clearFilters() {
    const filterButtons = qsa('.filterButton');
    filterButtons.forEach(button => {
        button.classList.add('inactive');
        button.dataset.active = 'false';
    });
    searchProjects();
}

function toggleSearchOptions() {
    const options = qs('#searchOptionsContainer');
    options.classList.toggle('hidden');
}

function activateFilterButton(button) {
    button.classList.toggle('inactive');
    button.dataset.active = button.classList.contains('inactive') ? 'false' : 'true';
}

function activateSortOption(button) {
    const sortButtons = qsa('.sortButton');
    if (!button.classList.contains('active')){
        sortButtons.forEach(btn => {
            btn.classList.remove('active')
            btn.querySelector('.arrow')?.remove();
        });
        button.classList.add('active');
        button.innerHTML += `<span class="material-symbols-rounded arrow">arrow_downward</span>`
        return
    }
    button.querySelector('.arrow').style.transform = button.querySelector('.arrow').style.transform === 'rotate(180deg)' ? 'rotate(0deg)' : 'rotate(180deg)';
    button.dataset.order = button.dataset.order === 'ASC' ? 'DESC' : 'ASC';
}


function searchProjects() {
    qs('.libraryLoader').classList.add('visible');
    const searchInput = qs('#projectSearchInput').value.toLowerCase().trim();
    const sortButton = qs('.sortButton.active');
    const sortBy = sortButton ? sortButton.dataset.sortBy : 'date_added';
    const sortOrder = sortButton ? sortButton.dataset.order : 'DESC';
    const filterButtons = Array.from(qsa('.filterButton'));
    const activeBadges = filterButtons
        .filter(btn => btn.dataset.active === 'true')
        .map(btn => btn.dataset.badgeIndex)
        .join(',');
    
    const projectsLibrary = qs('#projectsLibrary');

    const params = new URLSearchParams({
        search: searchInput,
        badges: activeBadges,
        sortBy: sortBy,
        sortOrder: sortOrder
    });
    
    const basePath = window.location.pathname.includes('/projects/') ? '../' : '';
    fetch(`${basePath}php/soneSearchSystem.php?${params}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.projects.length > 0) {
                projectsLibrary.innerHTML = data.projects.map(renderProject).join('');
                waitForImages(projectsLibrary).then(() => {
                    qs('.libraryLoader').classList.remove('visible');
                });
            } else {
                projectsLibrary.innerHTML = `
                <div class="noProjectsMessage">
                    <span class="material-symbols-rounded">sentiment_dissatisfied</span>
                    Brak projektów
                </div>`;
                qs('.libraryLoader').classList.remove('visible');
            }
        })
        .catch(err => {
            console.error('Błąd ładowania projektów:', err);
            qs('.libraryLoader').classList.remove('visible');
        });
        
}