function qs(a) {return document.querySelector(a);}
function qsa(a) {return document.querySelectorAll(a);}

function getDayStart(now,selectedStartingDay) {
  let startingDay = ''
  if(selectedStartingDay) {startingDay = selectedStartingDay;} else { startingDay = '5'};
  let dayOfWeek = now.getDay();
  let offset = (startingDay - dayOfWeek + 7) % 7;
  let friday = new Date(now);
  friday.setDate(now.getDate() + offset);

  if (dayOfWeek > 1) {
    friday.setDate(friday.getDate() + 7);
  }
  if (dayOfWeek === 1 && now.getHours() === 23 && now.getMinutes() === 59) {
    friday.setDate(friday.getDate() + 7);
  }
  return {friday, startingDay};
}

function formatDate(date) {
  return (
    (date.getDate() < 10 ? '0' : '') + date.getDate() + '.' +
    ((date.getMonth() + 1) < 10 ? '0' : '') + (date.getMonth() + 1)
  );
}

function showDayOptions() {
  const dayOptions = qs('.dayOptions') 
  dayOptions.style.height = "200px"
}

function hideDayOptions() {
  const dayOptions = qs('.dayOptions') 
  dayOptions.style.height = "0px"
}

function getWeekdayShort(index) {
  const daysShort = ["nd.", "pon.", "wt.", "śr.", "czw.", "pt.", "sob."];
  return daysShort[index];
}

function renderDispoForm(selectedStartingDay) {
  const days = ["Poniedziałek", "Wtorek", "Środa", "Czwartek", "Piątek", "Sobota", "Niedziela"];
  let now = new Date();
  let friday = getDayStart(now, selectedStartingDay).friday;
  let firstDay = '';
  let lastDay = '';
  const form = qs('#dispoForm');
  const selectedDay = qs('#selectedDay');
  form.innerHTML = "";
  selectedDay.innerText = `${days[(getDayStart(now, selectedStartingDay).startingDay) - 1]}`

  for (let i = 0; i < 7; i++) {
    let date = new Date(friday);
    date.setDate(friday.getDate() + i);
    let dayName = getWeekdayShort(date.getDay());
    let label = formatDate(date);
    if(i == 0) {
      firstDay = label;
    }
    if(i == 6) {
      lastDay = label;
    }

    form.innerHTML += `
      <div class="day">
        <h2>${dayName} <label>${label}</label></h2>

        <div class="timeDiv"> 
        <input type="time" id="timeStart${dayName}" autocomplete="off" onclick="picker(this)" /> <span id="span${dayName}" class="material-symbols-rounded lock" onclick="enableInput('${dayName}')">lock</span> <p>-<p> 
        <input type="time" id="timeEnd${dayName}" autocomplete="off" onclick="picker(this)"/> <span id="span${dayName}" class="material-symbols-rounded lock" onclick="enableInput('${dayName}')">lock</span> 
        </div>
        <p>
         <p><input type="radio" name="radio${dayName}" id="radio1${dayName}" onclick="disableInput('${dayName}')" value="Cały dzień"/> Cały dzień </p>
        <p><input type="radio" name="radio${dayName}" id="radio2${dayName}" onclick="disableInput('${dayName}')" value="-"/> Wolne </p>
        </p>
      </div>
    `;
  } 
  return {firstDay, lastDay};
}

function disableInput(id) {
    
    const timeStart = document.getElementById('timeStart'+id);
    const timeEnd = document.getElementById('timeEnd'+id);
    const lockIcon = document.getElementById('span'+id);
    
    timeStart.value = 'null';
    timeStart.disabled = true;
    timeEnd.value = 'null';
    timeEnd.disabled = true
    lockIcon.style.display = "block";

}

function enableInput(id) {
  const timeStart = document.getElementById('timeStart' + id);
  const timeEnd = document.getElementById('timeEnd' + id);
  const lockIcon = document.getElementById('span' + id);
  const inputRadio1 = document.getElementById('radio1' + id);
  const inputRadio2 = document.getElementById('radio2' + id);
    
    timeStart.value = 'null';
    timeStart.disabled = false;
    timeEnd.value = 'null';
    timeEnd.disabled = false;
    lockIcon.style.display = "none";
    inputRadio1.checked = false;
    inputRadio2.checked = false;
}

function generateTable() {
  qs('#dayAlertIcon').textContent  = "acute";
  const dni = qsa('#dispoForm .day');
  const daysTable = [];
  const dyspoTable = [];


  let checker = 0;

  const alertDay = qs('.alertDay');
  alertDay.querySelector('p').textContent  = "Wypełnij wszystkie dni"; 
  dni.forEach(dayField=> {
      let radioValue = null;
      const dayShort = dayField.querySelector('h2').childNodes[0].textContent.trim(); 
     const dayName = dayField.querySelector('label').textContent.trim();
     const timeStart = document.getElementById('timeStart'+dayShort);
     const timeEnd = document.getElementById('timeEnd'+dayShort);
  
  if(timeStart.value == '' && timeEnd.value == '') {
    const radios = dayField.querySelectorAll('input[type="radio"]');
    radios.forEach(radio => {
      if(radio.checked) {
        radioValue = radio.value;
        checker += 1;
      }
    })
  } else {
    checker += 1;
  }
  let fullTime = `${timeStart.value}-${timeEnd.value}`
  if(timeStart.value == '') {fullTime = `do ${timeEnd.value}`}
  if(timeEnd.value == '') {fullTime = `od ${timeStart.value}`}
  daysTable.push(dayName);
  dyspoTable.push(radioValue != null ? radioValue : fullTime);
  })
   if(checker < 7) {
    alertDay.style.bottom = '100px';
    setTimeout(()=>{
      alertDay.style.bottom = '-450px';
    },950)
      throw new Error(`Brak danych dla ${dayName}`);
    }

    let headerRow = '<tr>';
  let valueRow = '<tr>';

  daysTable.forEach((day, index) => {
    headerRow += `<th><span class="divider">| </span>${day}</th>`;
    valueRow += `<td><span class="divider">| </span>${dyspoTable[index]}</td>`;
  });

  headerRow += '</tr>';
  valueRow += '</tr>';

  const tableHtml = `
    <table class="finalTable">
      ${headerRow}
      ${valueRow}
    </table>`

    qs("#tb").innerHTML = tableHtml

    copyHtmlTable()
}

function clearForm() {
  qs('#dispoForm').reset();
  inputs = qsa('input[type="time"]');
  lockIcons = qsa('.lock');

  inputs.forEach(input => {
    input.disabled = false;
  });
  lockIcons.forEach(icon => {
    icon.style.display = "none";
  });
}

renderDispoForm();

async function copyHtmlTable() {
  const tabela = qs('.finalTable');
  try {
    const alertSuccess = qs('.alertSuccess');
    const alertError = qs('.alertError');

    const htmlData = tabela.outerHTML;
    const plainText = tabela.innerText;
    const style = `
    <style>
        .finalTable {
          padding: 15px;
          background-color: #FFFFFF;
          border-collapse: separate;
          border-spacing: 0;
          border-radius: 10px;
          overflow: hidden;
          width: fit-content;
          font-family: Nunito, Arial, Helvetica, sans-serif;
        }
        .divider {
          display: none
        }
        .finalTable th, .finalTable td {
          border-collapse: collapse;
          padding: 5px;
          text-align: center;
        }
        .finalTable th {
          color: #1A1110;
          border-radius: 12px 12px 0px 0px;
        }
        .finalTable td {
          color: #5e6064;
          border-radius: 0px 0px 12px 12px;
        }
        .finalTable th:nth-child(even),
        .finalTable td:nth-child(even) {
          background-color: #F9FAFB;
        }
        .finalTable th:nth-child(odd),
        .finalTable td:nth-child(odd) {
          background-color: #FFFFFF;
        }
      </style>
    `;

    const fullHtmlData = `
     <html>
      <head>${style}</head>
      <body>${htmlData}</body>
    </html>
    `


    await navigator.clipboard.write([
      new ClipboardItem({
        'text/html': new Blob([fullHtmlData], { type: 'text/html' }),
        'text/plain': new Blob([plainText], { type: 'text/plain' })
      })
    ]);
    alertSuccess.style.bottom = '100px';
    setTimeout(()=>{
      alertSuccess.style.bottom = '-450px';
    },850)
  } catch (err) {
    alertError.style.bottom = '100px';
    setTimeout(()=>{
      alertError.style.bottom = '-450px';
    },850)
    console.error(err);
  }
}
function sendMail() {
  const personData = qs('#personData').value;
  if(personData.trim() === '') {
      const alertDay = qs('.alertDay'); 
      alertDay.querySelector('p').textContent  = "Wprowadź imię i nazwisko";
      qs('#dayAlertIcon').textContent  = "account_circle";
      alertDay.style.bottom = '100px';
    setTimeout(()=>{
      alertDay.style.bottom = '-450px';
    },950)
    return
  }
  generateTable();
  const {firstDay, lastDay} = renderDispoForm();
  const Title = `Dyspozycyjność ${firstDay}-${lastDay} - ${personData} `
  const url = 'https://mail.google.com/mail/?view=cm&fs=1'
   + '&to=' + encodeURIComponent()
   + '&su=' + encodeURIComponent(Title)
   + '&body=' + encodeURIComponent('wklej')
   + '&tf=cm';
  const urlMobile = 'mailto:' 
    + '?subject=' + encodeURIComponent(Title)
    + '&body=' + encodeURIComponent('wklej');
  if(window.innerWidth < 1000) {
    window.location.href = urlMobile;
  } else {
    window.open(url, '_blank');
  }

}
function picker(el) {
    if (el.showPicker) {
      el.showPicker(); // otworzy natywny picker czasu
    }
}

(function() {
  const endElement = document.getElementById('endOfPage');
  if (!endElement) return;

  const buffer = 15;

  window.addEventListener('scroll', () => {
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    const viewportHeight = window.innerHeight;
    const endTop = endElement.getBoundingClientRect().top + scrollTop;

    const maxScroll = endTop + buffer - viewportHeight;

    if (scrollTop > maxScroll) {
      window.scrollTo({
        top: maxScroll,
        behavior: 'instant' 
      });
    }
  });
})();

