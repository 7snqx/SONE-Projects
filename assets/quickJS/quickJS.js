function qs(a){return document.querySelector(a);}
function qsa(a) {return document.querySelectorAll(a);}
function zeroPad(n) {return n < 10 ? '0'+n : n}

function createScrollObserver(config) {
  const {
    watchElement,           // Element obserwowany
    onVisible,             // Co robić gdy widoczny
    onHidden,              // Co robić gdy niewidoczny
    threshold = 0.5        // Przy ilu % widoczności się triggera
  } = config;

  const observer = new IntersectionObserver(
    (entries) => {
      if (entries[0].isIntersecting) {
        onVisible?.();  // Bez parametru
      } else {
        onHidden?.();   // Bez parametru
      }
    },
    { threshold }
  );

  observer.observe(watchElement);
  return observer;
}
//* PRZYKŁADY UŻYCIA
//* ========================================

//* Go to Top Button
// createScrollObserver({
//   watchElement: document.getElementById('welcomeBlock'),
//   onVisible: () => document.getElementById('gototop').style.display = 'none',
//   onHidden: () => document.getElementById('gototop').style.display = 'block'
// });

//* Animacja karty
// createScrollObserver({
//   watchElement: document.getElementById('section2'),
//   onVisible: () => document.getElementById('card').classList.add('hidden'),
//   onHidden: () => document.getElementById('card').classList.remove('hidden'),
//   threshold: 0.1
// });

//* Wiele zmian naraz
// createScrollObserver({
//   watchElement: document.getElementById('section'),
//   onVisible: () => {
//     document.getElementById('btn1').style.display = 'none';
//     document.getElementById('btn2').style.display = 'block';
//   },
//   onHidden: () => {
//     document.getElementById('btn1').style.display = 'block';
//     document.getElementById('btn2').style.display = 'none';
//   }
// });

//* Dynamicznie dodaj element
// createScrollObserver({
//   watchElement: document.getElementById('trigger'),
//   onHidden: () => {
//     const card = document.createElement('div');
//     card.textContent = 'Nowy card';
//     document.body.appendChild(card);
//   }
// });

//* forEach - Wiele elementów
// document.querySelectorAll('.card').forEach(card => {
//   createScrollObserver({
//     watchElement: card,
//     onVisible: () => card.classList.add('visible'),
//     onHidden: () => card.classList.remove('visible')
//   });
// });

// EASING – zwalnia pod koniec

function waitForImages(container) {
    const images = container.querySelectorAll('img');
    if (images.length === 0) return Promise.resolve(true);
    const promises = Array.from(images).map(img => {
        if (img.complete) return Promise.resolve();
        return new Promise(resolve => {
            img.addEventListener('load', resolve);
            img.addEventListener('error', resolve);
        });
    });
    return Promise.all(promises);
}

function easeOutQuad(t) {
  return t * (2 - t);
}

function animateNumber(element, targetNumber, duration) {
  if (element.classList.contains('animated')) return;
  const startTime = Date.now();
  
  function update() {
    const now = Date.now();
    let progress = (now - startTime) / duration; // 0 → 1
    
    if (progress > 1) progress = 1;
    
    const easedProgress = easeOutQuad(progress);
    const currentNumber = Math.floor(targetNumber * easedProgress);
    
    element.textContent = currentNumber;
    
    if (progress < 1) {
      requestAnimationFrame(update);
    } else {
      element.classList.add('animated');
    }
  }
  
  update();
}