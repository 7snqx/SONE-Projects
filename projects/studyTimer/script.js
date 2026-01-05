 function qs(a){return document.querySelector(a);}
 function qsa(a) {return document.querySelectorAll(a);}
 function zeroPad(n) {return n < 10 ? '0'+n : n}
 function setCssVar(a,b) {return document.documentElement.style.setProperty(a,b)}

const timer = qs('#timer');
const timeLeft = qs('#timeLeft');
let timerInterval;
let breakInterval;
const settings = qs('#settings');
const inputs = qsa('input');
const X = qs('#X')
let i=0
let remainingStudyTime;
let remainingTotalTime;
let remainingBreakTime;
const studyAudio = new Audio('assets/mp3/study.wav');
const breakAudio = new Audio('assets/mp3/break.wav');
const finishAudio = new Audio('assets/mp3/finish.mp3')

setTimeout(()=> {
  window.onload = togleSettings('show')
}, 300)




function togleSettings(a) {
  if(a == "show") {
    X.style.rotate = '0deg'  
    settings.style.width = '400px'
    settings.style.padding = '15px'
    setTimeout(()=>{
        inputs.forEach(input => {
                input.style.opacity = '1';
            });
      settings.style.fontSize = '15px'
   },200)
  } else {  
          X.style.rotate = '180deg'   
         settings.style.fontSize = '0px'
         inputs.forEach(input => {
          input.style.opacity = '0';
      });
    setTimeout(()=>{
        settings.style.padding = '0'
      settings.style.width = '0'
   },200)


  }
}

function resetValues(commandButtons) {
  setCssVar('--fontColor', '#ff868a');
  setCssVar('--fontColor2', '#e9e8e8');
  setCssVar('--textShadow', '#fc5e63');
  setCssVar('--bacgroundColor', '#FF454B');
  setCssVar('--bacgroundColor2', '#ff2e35');
 
  const command = 'reset';
  reset(command)
  commandButtons == 'reset' ? pause(command) : 0;
  
 
}

function checkTime() {

  if(qs('#time').value == 0 || qs('#studyTime').value == 0 || qs('#breakTime').value == 0) {alert("ERROR: CZAS NIE MOŻE BYĆ RÓWNY 0"); return}

  const time = ((qs('#time').value)*60*60)+1;
  let studyTime = ((qs('#studyTime').value)*60)+1;
  const breakTime = ((qs('#breakTime').value)*60)+1;

  let sessions = Math.floor(time/studyTime)
  let lastSession = time - studyTime*sessions

  if(sessions < 1) {studyTime = time}

  // console.log(qs('#studyTime').value + ' sT')
  // console.log(studyTime + ' sT2')
  // console.log(sessions + ' sess')
  // console.log(lastSession + ' lastSess')
  return {time,studyTime,breakTime,sessions,lastSession}
}

function studyTimer() {
  if (timerInterval) clearInterval(timerInterval);
  studyAudio.play()
  setCssVar('--fontColor', '#ff868a');
  setCssVar('--fontColor2', '#e9e8e8');
  setCssVar('--textShadow', '#fc5e63');
  setCssVar('--bacgroundColor', '#FF454B');
  setCssVar('--bacgroundColor2', '#ff2e35');

  let { time, studyTime, breakTime, sessions, lastSession } = checkTime();
  const timeLeft = qs("#timeLeft");

  let sTime = (remainingStudyTime && remainingStudyTime > 0) ? remainingStudyTime : studyTime;
  let tTime = (remainingTotalTime && remainingTotalTime > 0) ? remainingTotalTime : time;

  timerInterval = setInterval(() => {
    sTime--;
    tTime--;
    remainingStudyTime = sTime; 
    remainingTotalTime = tTime;
    if (sTime >= 60 * 60) {
      let h = zeroPad(Math.floor((sTime / 60) / 60) % 60);
      let m = zeroPad(Math.floor(sTime / 60) % 60);
      timer.innerHTML = `${h}:${m}`;

      let hT = zeroPad(Math.floor((tTime / 60) / 60) % 60);
      let mT = zeroPad(Math.floor(tTime / 60) % 60);
      timeLeft.innerHTML = `${hT}:${mT}`;
      timeLeft.value = tTime;
    } else if (sTime >= 0) {
      let m = zeroPad(Math.floor(sTime / 60) % 60);
      let s = zeroPad(Math.floor(sTime % 60));
      timer.innerHTML = `${m}:${s}`;

      let hT = zeroPad(Math.floor((tTime / 60) / 60) % 60);
      let mT = zeroPad(Math.floor(tTime / 60) % 60);
      timeLeft.innerHTML = `${hT}:${mT}`;
      timeLeft.value = tTime;
    } else {
      startBreak();
      clearInterval(timerInterval);
    }
  }, 1000);
}

function startBreak() {
  if(breakInterval) clearInterval(breakInterval) 
    breakAudio.play()
    setCssVar('--fontColor', '#86acff');
    setCssVar('--fontColor2', '#e8e9e9');
    setCssVar('--textShadow', '#5e95fc');
    setCssVar('--bacgroundColor', '#4580ff');
    setCssVar('--bacgroundColor2', '#2e8fff');

    let { time, studyTime, breakTime, sessions, lastSession } = checkTime();

    let bTime = remainingBreakTime > 0 ? remainingBreakTime : breakTime;

  breakInterval = setInterval(() => {
    bTime--;
    remainingBreakTime = bTime;
    if(bTime>=60*60) {
        let h = zeroPad(Math.floor((bTime/60)/60)%60);
        let m = zeroPad(Math.floor(bTime/60)%60);
        timer.innerHTML = `${h}:${m}`;
    } else if(bTime>=0) {
        let h = zeroPad(Math.floor((bTime/60)/60)%60);
        let m = zeroPad(Math.floor(bTime/60)%60);
        let s = zeroPad(Math.floor(bTime % 60));
        timer.innerHTML = `${m}:${s}`;
    } else {startNextSession();  clearInterval(breakInterval) }

  }, 1000); //zmien to
}

function lastSessionTimer() {
  studyAudio.play()
  i=0
  if (timerInterval) clearInterval(timerInterval);

  setCssVar('--fontColor', '#ff868a');
  setCssVar('--fontColor2', '#e9e8e8');
  setCssVar('--textShadow', '#fc5e63');
  setCssVar('--bacgroundColor', '#FF454B');
  setCssVar('--bacgroundColor2', '#ff2e35');

  let { time, studyTime, breakTime, sessions, lastSession } = checkTime();
  const timeLeft = qs("#timeLeft");

  console.log(remainingStudyTime, studyTime)
  let sTime = (remainingStudyTime && remainingStudyTime > 0) ? remainingStudyTime : lastSession;
  let tTime = (remainingTotalTime && remainingTotalTime > 0) ? remainingTotalTime : time;

  timerInterval = setInterval(() => {
    sTime--;
    tTime--;
    remainingStudyTime = sTime; 
    remainingTotalTime = tTime;
    if (sTime >= 60 * 60) {
      let h = zeroPad(Math.floor((sTime / 60) / 60) % 60);
      let m = zeroPad(Math.floor(sTime / 60) % 60);
      timer.innerHTML = `${h}:${m}`;

      if( tTime > 0) {
        let hT = zeroPad(Math.floor((tTime / 60) / 60) % 60);
        let mT = zeroPad(Math.floor(tTime / 60) % 60);
        timeLeft.innerHTML = `${hT}:${mT}`;
        timeLeft.value = tTime;
      }
    } else if (sTime >= 0) {
      let m = zeroPad(Math.floor(sTime / 60) % 60);
      let s = zeroPad(Math.floor(sTime % 60));
      timer.innerHTML = `${m}:${s}`;
      
      if( tTime > 0) {
        let hT = zeroPad(Math.floor((tTime / 60) / 60) % 60);
        let mT = zeroPad(Math.floor(tTime / 60) % 60);
        timeLeft.innerHTML = `${hT}:${mT}`;
        timeLeft.value = tTime;
      }
    } else {
      setCssVar('--fontColor', '#0ea74e');
      setCssVar('--fontColor2', '#e9e8e8');
      setCssVar('--textShadow', '#0ea74e');
      setCssVar('--bacgroundColor', '#358c4b');
      setCssVar('--bacgroundColor2', '#358c35');
      clearInterval(timerInterval);
      finishAudio.play()
    }
  }, 1000);
}


const pauseButtons = qs('.pauseButtons');
const startBtn = qs('.start');

function startTimer() {
  togleSettings()
  let { time, studyTime, breakTime, sessions, lastSession } = checkTime();
  pauseButtons.style.height = 0;
  startBtn.style.height = 0;
  setTimeout(()=>{
    timer.style.fontSize = '25vw'
    timer.style.cursor = 'pointer'
    timer.setAttribute('onclick', 'pause()')
  },200)
    // console.log(i + ' i', sessions)
  if( i < sessions) {
  
    i++
    studyTimer();
  } else {
    lastSessionTimer();
  }
}
// console.log('v1:'+i)

function startNextSession() {
  let { sessions } = checkTime(); 
 
  if (i < sessions) {
    i++;  
    // console.log('asdasda'+i)
    studyTimer();  
  } else {
    lastSessionTimer();  
  }
}


function pause(command) {
  clearInterval(breakInterval);
  clearInterval(timerInterval);
  timer.removeAttribute('onclick', 'pause()')
  timer.style.fontSize = '20vw'
  timer.style.cursor = 'auto'
  let Buttons = command == 'reset' ? startBtn : pauseButtons;
  setTimeout(()=> {
    Buttons.style.height = '300px'
  },200)
}

function unPause() {
  pauseButtons.style.height = 0;
  startBtn.style.height = 0;
  setTimeout(()=>{
    timer.style.fontSize = '25vw'
    timer.style.cursor = 'pointer'
    timer.setAttribute('onclick', 'pause()')
  },200)
  
  if(remainingStudyTime > 0) {
    studyTimer()
  } else if (remainingBreakTime > 0) {
    startBreak()
  }
}

function reset(command) {
  setCssVar('--fontColor', '#ff868a');
  setCssVar('--fontColor2', '#e9e8e8');
  setCssVar('--textShadow', '#fc5e63');
  setCssVar('--bacgroundColor', '#FF454B');
  setCssVar('--bacgroundColor2', '#ff2e35');
  let { time, studyTime, breakTime, sessions, lastSession } = checkTime();
  remainingBreakTime =  breakTime;
  remainingStudyTime = studyTime
  remainingTotalTime = time
  clearInterval(breakInterval);
  clearInterval(timerInterval);
  i =  0
  // console.log(i)
  timer.innerText = '00:00'
  timeLeft.innerText = '00:00'

}

function resetBtns() {
  pauseButtons.style.height = 0;
  setTimeout(()=>{
    startBtn.style.height = '300px';
  },400)
}
    
