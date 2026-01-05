// ========================================
// MODAL FUNCTIONS
// ========================================
function openModal(modalType) {
    document.getElementById('modalOverlay').classList.add('active');
    document.getElementById(modalType + 'Modal').classList.add('active');
}

function closeModal() {
    document.getElementById('modalOverlay').classList.remove('active');
    document.querySelectorAll('.modal').forEach(m => m.classList.remove('active'));
}

// ========================================
// PROJECT FUNCTIONS
// ========================================
function editProject(id, title, url, image, badge, description) {
    document.getElementById('editProjectId').value = id;
    document.getElementById('editProjectTitle').value = title;
    document.getElementById('editProjectUrl').value = url;
    document.getElementById('editProjectImage').value = image;
    document.getElementById('editProjectBadge').value = badge || '';
    document.getElementById('editProjectDescription').value = description || '';
    openModal('editProject');
}

// ========================================
// UPDATE FUNCTIONS
// ========================================
function deleteUpdate(id) {
    if(confirm('Czy na pewno chcesz usunąć tę aktualizację?')) {
        window.location.href = 'php/delUpdate.php?id=' + id;
    }
}

// ========================================
// PLAN FUNCTIONS
// ========================================
function togglePlan(id, completed) {
    window.location.href = 'php/modifyPlan.php?id=' + id + '&completed=' + completed;
}

// ========================================
// NAVIGATION
// ========================================
function scrollToSection(sectionId) {
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        
        // Update active nav item
        document.querySelectorAll('.navItem').forEach(item => {
            item.classList.remove('active');
        });
        event.target.closest('.navItem').classList.add('active');
    }
}

// ========================================
// KEYBOARD SHORTCUTS
// ========================================
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});

// ========================================
// SCROLL SPY FOR NAVIGATION
// ========================================
document.addEventListener('DOMContentLoaded', function() {
    const mainContent = document.querySelector('.mainContent');
    if (!mainContent) return;

    const sections = ['projectsSection', 'updatesSection', 'plansSection'];
    
    mainContent.addEventListener('scroll', function() {
        let currentSection = sections[0];
        
        sections.forEach(sectionId => {
            const section = document.getElementById(sectionId);
            if (section) {
                const rect = section.getBoundingClientRect();
                const mainRect = mainContent.getBoundingClientRect();
                
                if (rect.top <= mainRect.top + 100) {
                    currentSection = sectionId;
                }
            }
        });
        
        document.querySelectorAll('.navItem').forEach(item => {
            item.classList.remove('active');
            if (item.getAttribute('onclick')?.includes(currentSection)) {
                item.classList.add('active');
            }
        });
    });
});
