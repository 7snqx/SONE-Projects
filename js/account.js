function goHome() {
    qs('.wholePageLoader').style.opacity = '1';
    setTimeout(() => {
        window.location.href = '../index.php';
    }, 300);
}

function switcher(key) {
    switch(key) {
        case 'login':
            qs('#login').style.display = 'flex';
            qs('#register').style.display = 'none';
            qs('#loginBtn').classList.add('active');
            qs('#registerBtn').classList.remove('active');
            break;
        case 'register':
            qs('#login').style.display = 'none';
            qs('#register').style.display = 'flex';
            qs('#loginBtn').classList.remove('active');
            qs('#registerBtn').classList.add('active');
            break;
        default:
            qs('#login').style.display = 'flex';
            qs('#register').style.display = 'none';
            qs('#loginBtn').classList.add('active');
            qs('#registerBtn').classList.remove('active');
            break;
    }
}

function rotateArrow(key) {
    qs('.errorMsg')?.remove();

    const loginArrow = qs('#submitArrowLog');
    const registerArrow = qs('#submitArrowReg');
    const arrow = loginArrow?.offsetParent !== null ? loginArrow : registerArrow;
    
    switch(key) {
        case 'usernameLogInpt':
        case 'usernameRegInpt':
        case 'emailRegInpt':
            arrow.style.transform = 'rotate(0deg)';
            break;
        case 'passwordLogInpt':
        case 'passwordRegInpt':
        case 'confirmPasswordRegInpt':
            arrow.style.transform = 'rotate(180deg)';
            break;
        case 'Submit':
            arrow.style.transform = 'rotate(90deg)';
            break;
        default:
            arrow.style.transform = 'rotate(0deg)';
            break;
    }
}

function checker(input) {
    setTimeout(() => {
    if(input.value.length < 1) {
        input.parentElement.classList.add('wrong');
        rotateArrow(input.id);
        setTimeout(() => {
            input.parentElement.classList.remove('wrong');
            const errorMsg = document.createElement('span');
            errorMsg.className = 'errorMsg';
            errorMsg.textContent = "To pole nie może być puste";
            input.parentElement.appendChild(errorMsg);
        }, 300);
    }
    }, 100);
}

function resetPasswordVisibility(action) {
    switch(action) {
        case 'Show':
            qs('.resetPassword').style.display = 'flex';
            break;
        case 'Hide':
            qs('.resetPassword').style.display = 'none';
            break;
        default:
            qs('.resetPassword').style.display = 'flex';
            break;
    }
}

const verifyEmailForm = qs('#verifyEmail');
const verifyCodeForm = qs('#verifyCode');

verifyEmailForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const emailInput = qs('#resetEmail').value.trim();
    if(!emailInput) return;

    fetch('../php/auth/verificationCodeSender.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(emailInput) + '&type=passwordReset'
        
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Verification code sent successfully.');
            verifyEmailForm.style.display = 'none';
            verifyCodeForm.style.display = 'flex';
        } else {
            console.error('Failed to send verification code:', data.message);
            const existingError = verifyEmailForm.querySelector('.errorMsg');
            if(existingError) {
                existingError.remove();
            }
            
            const errorMsg = document.createElement('span');
            errorMsg.className = 'errorMsg';
            errorMsg.textContent = data.message;
            verifyEmailForm.appendChild(errorMsg);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });

});

verifyCodeForm?.addEventListener('submit', (e) => {
    e.preventDefault();
    const codeInput = qs('#verificationCode').value.trim();
    if(!codeInput) return;
    fetch('../php/auth/verificationCodeValidator.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'code=' + encodeURIComponent(codeInput)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Verification code validated successfully.');
            const formsContainer = qs('.forms');
            formsContainer.innerHTML = `
            <form id="newPasswordForm" class="newPasswordForm">
                <label for="passwordNew">Nowe hasło</label>
                <input type="password" id="passwordNew" name="passwordNew" placeholder="Wpisz nowe hasło">
                <label for="passwordConfirm">Potwierdź hasło</label>
                <input type="password" id="passwordConfirm" name="passwordConfirm" placeholder="Potwierdź nowe hasło">
                <button type="submit">Zmień hasło</button>
            </form>
            `
            const newPasswordForm = qs('#newPasswordForm');
            newPasswordForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const newPassword = qs('#passwordNew').value.trim();
                const confirmPassword = qs('#passwordConfirm').value.trim();
                if(!newPassword || !confirmPassword) return;
                if(newPassword !== confirmPassword) {
                    const existingError = newPasswordForm.querySelector('.errorMsg');
                    if(existingError) {
                        existingError.remove();
                    }
                    const errorMsg = document.createElement('span');
                    errorMsg.className = 'errorMsg';
                    errorMsg.textContent = 'Hasła nie są zgodne.';
                    newPasswordForm.appendChild(errorMsg);
                    return;
                }
                fetch('../php/auth/sonePasswordResetSystem.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'newPassword=' + encodeURIComponent(newPassword)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Password reset successfully.');
                        window.location.reload();
                    } else {
                        console.error('Failed to reset password:', data.message);
                        const existingError = newPasswordForm.querySelector('.errorMsg');
                        if(existingError) {
                            existingError.remove();
                        }
                        const errorMsg = document.createElement('span');
                        errorMsg.className = 'errorMsg';
                        errorMsg.textContent = data.message;
                        newPasswordForm.appendChild(errorMsg);
                    }
                })
            });
        } else {
            console.error('Failed to validate verification code:', data.message);
            const existingError = verifyCodeForm.querySelector('.errorMsg');
            if(existingError) {
                existingError.remove();
            }
            
            const errorMsg = document.createElement('span');
            errorMsg.className = 'errorMsg';
            errorMsg.textContent = data.message;
            verifyCodeForm.appendChild(errorMsg);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });

});

const verifyEmailBtn = qs('#verifyEmailBtn');

verifyEmailBtn?.addEventListener('click', () => {
    const emailText = qs('.importantNotice p b')?.textContent.trim();
    if(!emailText) return;

    fetch('../php/auth/verificationCodeSender.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'email=' + encodeURIComponent(emailText) + '&type=emailVerify'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            verifyEmailBtn.outerHTML = `
                <form id="emailVerificationForm" class="emailVerificationForm">
                    <input type="text" placeholder="Kod weryfikacyjny został wysłany na Twój email">
                    <button type="submit">
                    <span class="material-symbols-rounded">mark_email_read</span>
                    Zweryfikuj email</button>
                </form>
            `;
            const emailVerificationForm = qs('#emailVerificationForm');
            emailVerificationForm.addEventListener('submit', (e) => {
                e.preventDefault();
                const codeInput = emailVerificationForm.querySelector('input').value.trim();
                if(!codeInput) return;
                fetch('../php/auth/verificationCodeValidator.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'code=' + encodeURIComponent(codeInput) + '&type=emailVerify'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Verification code validated successfully.');
                        const notice = qs('.importantNotice');
                        notice.innerHTML = `
                             <div class="sectionHeader">
                                <span class="material-symbols-rounded">verified</span>
                                <h3>Twoje konto zostało zweryfikowane</h3>
                            </div>
                        `;
                        notice.classList.add('successNotice');
                        notice.classList.remove('importantNotice');
                    } else {
                        console.error('Failed to validate verification code:', data.message);
                        const existingError = emailVerificationForm.querySelector('.errorMsg');
                        if(existingError) {
                            existingError.remove();
                        }
                        const errorMsg = document.createElement('span');
                        errorMsg.className = 'errorMsg';
                        errorMsg.textContent = data.message;
                        emailVerificationForm.appendChild(errorMsg);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
            });
        } else {
            verifyEmailBtn.innerHTML = '<span class="material-symbols-rounded">error</span>Błąd wysyłania';
            console.error('Failed to send verification code:', data.message);
            setTimeout(() => {
                verifyEmailBtn.innerHTML = '<span class="material-symbols-rounded">email</span>Wyślij email weryfikacyjny';
                verifyEmailBtn.disabled = false;
            }, 3000);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        verifyEmailBtn.innerHTML = '<span class="material-symbols-rounded">error</span>Błąd połączenia';
        setTimeout(() => {
            verifyEmailBtn.innerHTML = '<span class="material-symbols-rounded">email</span>Wyślij email weryfikacyjny';
            verifyEmailBtn.disabled = false;
        }, 3000);
    });
});

function dataChange(key) {
    let newData, type;
    switch(key) {
        case 'username':
            type = 'username';
            newData = qs('#editUsername').value.trim();
            if(!newData) return;
            break;
        case 'email':
            type = 'email';
            newData = qs('#editEmail').value.trim();
            if(!newData) return;
            break;
        case 'password':
            type = 'password';
            const password = qs('#newPassword').value.trim();
            const passwordConfirm = qs('#confirmNewPassword').value.trim();
            const currentPassword = qs('#currentPassword').value.trim();
            newData = JSON.stringify({ newPassword: password, passwordConfirm: passwordConfirm, currentPassword: currentPassword });
            if(!newData) return;
            break;
    }
    fetch('../php/soneDataChangeSystem.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'type=' + type + '&newData=' + encodeURIComponent(newData)
        
    })
    .then(response => response.json())
    .then(data => {
        const popupAlert = qs('#popupAlert');
        if (data.success) {
            console.log('Data changed successfully.');
            popupAlert.style.backgroundColor = 'var(--accent-green-bg)';
            popupAlert.style.outline = '2px solid var(--accent-green-border)';
            popupAlert.style.color = 'var(--accent-green-text)';
            popupAlert.innerHTML = `
                <span class="material-symbols-rounded">check_circle</span>
                ${data.message}
            `;
            popupAlert.style.display = 'flex';
            setTimeout(() => {
                popupAlert.style.display = 'none';
            }, 3000);

        } else {
            console.error('Failed to change data:', data.message);
            popupAlert.style.backgroundColor = 'var(--accent-red-bg)';
            popupAlert.style.outline = '2px solid var(--accent-red-border)';
            popupAlert.style.color = 'var(--accent-red-text)';
            popupAlert.innerHTML = `
                <span class="material-symbols-rounded">error</span>
                ${data.message}
            `;
            popupAlert.style.display = 'flex';
            setTimeout(() => {
                popupAlert.style.display = 'none';
            }, 3000);
        }
    }).catch(error => {
        console.error('Error:', error);
        const popupAlert = qs('#popupAlert');
        popupAlert.style.backgroundColor = 'var(--accent-red-bg)';
        popupAlert.style.outline = '2px solid var(--accent-red-border)';
        popupAlert.style.color = 'var(--accent-red-text)';
        popupAlert.innerHTML = `
            <span class="material-symbols-rounded">error</span>
            Wystąpił błąd połączenia
        `;
        popupAlert.style.display = 'flex';
        
        setTimeout(() => {
            popupAlert.style.display = 'none';
        }, 3000);
    });
}

function toggleProjectList(action) {
    const projectListContainer = qs('.projectsContainer');
    switch(action) {
        case 'show':
            projectListContainer.style.display = 'block';
            break;
        case 'hide':
            projectListContainer.style.display = 'none';
            break;
        default:
            projectListContainer.style.display = 'none';
            break;
    }
}
function projectsList() {
    fetch('../php/soneProjectsLoader.php')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const projectsContainer = qs('.projectsList');
            projectsContainer.innerHTML = '';
            data.projects.forEach(project => {
                bookmarked = data.bookmarkedIds.includes(project.id.toString()) ? 'bookmarked' : '';
                projectsContainer.innerHTML +=
               `
                    <div class="projectItem">
                        <div class="projectIcon">
                            <img src="../assets/img/${project.photo}" alt="${project.title}" />
                        </div>
                        <div class="projectInfo">
                            <p class="projectTitle">${project.title}</p>
                            <p class="projectUrl">projects/${project.url}</p>
                        </div>
                        <span class="material-symbols-rounded bookmarkIcon ${bookmarked}" onclick="bookmark(${project.id}, this)">bookmark_star</span>
                    </div>
               `
            });
        } else {
            console.error('Failed to load projects:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });


}

function bookmark(projectId, element) {
    const type = element.classList.contains('bookmarked') ? 'remove' : 'add';
    fetch('../php/soneBookmarkToggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'projectId=' + encodeURIComponent(projectId) + '&type=' + encodeURIComponent(type)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            element.classList.toggle('bookmarked');
            console.log(data.message);
        } else {
            console.error('Failed to toggle bookmark:', data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
    });
}

window.onload = () => {
    document.activeElement?.blur();
    projectsList();
    qs('.wholePageLoader').style.opacity = '0';
};
