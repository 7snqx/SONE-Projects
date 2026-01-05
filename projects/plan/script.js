function qs(a) {return document.querySelector(a);}
function qsa(a) {return document.querySelectorAll(a);}
function timeToMinutes(time) {
    const [hours, minutes] = time.split(':').map(num => parseInt(num, 10));
    return hours * 60 + minutes;
}


function getCurrentLesson() {
    const currentTime = new Date();
    const currentHour = currentTime.getHours();
    const currentMinutes = currentTime.getMinutes();
    const currentTimeInMinutes = currentHour * 60 + currentMinutes; 
    // const currentTimeInMinutes = currentHour * 60 + currentMinutes; 

    const daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday']; 
    const currentDayIndex = currentTime.getDay();

  
    const currentLesson = qs('#currentLesson');
    const nextLesson = qs('#nextLesson');

    let foundCurrentLesson = false;
    let nextLessonFound = false;
    let nextDayLesson = null;

    let nextDayIndex = (currentDayIndex + 1) % 7;

    let lessonCheckedForNextDay = false;
    let minutesUntil;
    let unit;
  
    for (let i = 0; i < lessons.length; i++) {
        const lesson = lessons[i];

        if (!lesson.subject) {
            continue; 
        }

        const lessonDayIndex = daysOfWeek.indexOf(lesson.day_of_week); 

        if(lessonDayIndex == 0 || lessonDayIndex == 6) {
            currentLesson.innerText = "Weekend, brak lekcji"
            
        }

       
        if (lessonDayIndex === currentDayIndex) {
            const startTimeInMinutes = timeToMinutes(lesson.start_time);
            const endTimeInMinutes = timeToMinutes(lesson.end_time);
           
            if (currentTimeInMinutes >= startTimeInMinutes && currentTimeInMinutes <= endTimeInMinutes) {
                minutesUntil = endTimeInMinutes - currentTimeInMinutes
                unit = 'min'
                if(minutesUntil < 1) {minutesUntil = minutesUntil*60; unit = 'sec'}
                currentLesson.innerHTML = ` <div>
                                                <h3>${lesson.subject} <label class="sala">${lesson.classroom}</label></h3>
                                                <p class="time">${(lesson.start_time).slice(0, -3)} -  ${(lesson.end_time).slice(0, -3)}</p>
                                            </div>
                                               <hr>
                                            <label class="min">zostało</label><p class="timeUntil">${minutesUntil}</p><label class="min">${unit}</label>`; 
                foundCurrentLesson = true; 

            } else if(currentTimeInMinutes < startTimeInMinutes && !nextLessonFound) {
                minutesUntil = startTimeInMinutes - currentTimeInMinutes
                unit = 'min'
                if(minutesUntil < 1) {minutesUntil = minutesUntil*60; unit = 'sec'}
                nextLesson.innerHTML = `<div>
                                            <h3>${lesson.subject} <label class="sala">${lesson.classroom}</label></h3>
                                            <p class="time">${(lesson.start_time).slice(0, -3)} -  ${(lesson.end_time).slice(0, -3)}</p>
                                        </div>
                                           <hr>
                                        <label class="min">za</label><p class="timeUntil">${minutesUntil}</p><label class="min">${unit}</label>`; 
                nextLessonFound = true; 
            }
        }
        if (!foundCurrentLesson && !lessonCheckedForNextDay && lessonDayIndex === nextDayIndex) {
            nextDayLesson = lesson;
            lessonCheckedForNextDay = true;
        }

        if (!foundCurrentLesson && !nextDayLesson) {
            currentLesson.innerHTML = `<h2>Przerwa</h2>`;
        }
        if (!foundCurrentLesson && nextDayLesson) {
            currentLesson.innerHTML = `<h2>Koniec lekcji na dziś</h2>`;
        }

        if (!nextLessonFound && nextDayLesson) {
            minutesUntil = parseFloat(((24 * 60 - currentTimeInMinutes) / 60).toFixed(1)) + parseFloat((nextDayLesson.start_time).slice(1, -6));
            nextLesson.innerHTML = `<div>
                                        <h3>${nextDayLesson.subject} <label class="sala">${nextDayLesson.classroom}</label></h3>
                                        <p class="time">${(nextDayLesson.start_time).slice(0, -3)} -  ${(nextDayLesson.end_time).slice(0, -3)}</p>
                                    </div>
                                    <hr>
                                   <label class="min">za</label><p class="timeUntil">${minutesUntil}</p><label class="min">h</label>`;
        }
       
        if (!nextLessonFound && !nextDayLesson) {
            nextLesson.innerText = '<h3>Brak następnej lekcji</h3>';
        }
    }

    console.log('dziala')
    
}
getCurrentLesson();

setInterval(()=> {
    getCurrentLesson();

}, 30 * 1000)

const timers =  qsa('.timeUntil');

setInterval(()=> {
    timers.forEach((timer)=> {
        timer.classList.add('animateTimer')
        setTimeout(() => {
            timer.classList.remove('animateTimer');
        }, 1000);
    })
}, 60 * 1000)