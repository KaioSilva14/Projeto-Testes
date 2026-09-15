const durations = {
  focus: 25 * 60,
  shortBreak: 5 * 60,
  longBreak: 15 * 60
};

const labels = {
  focus: 'Hora de se concentrar.',
  shortBreak: 'Descanse um pouco.',
  longBreak: 'Você merece uma pausa maior.'
};

let currentMode = 'focus';
let timeLeft = durations[currentMode];
let timerId = null;
let cyclesCompleted = 0;

const timerElement = document.querySelector('#timer');
const statusElement = document.querySelector('#status');
const startButton = document.querySelector('#startButton');
const resetButton = document.querySelector('#resetButton');
const cyclesElement = document.querySelector('#cycles');
const progressBar = document.querySelector('#progressBar');
const modeButtons = document.querySelectorAll('.mode-button');

function formatTime(seconds) {
  const minutes = Math.floor(seconds / 60).toString().padStart(2, '0');
  const remainingSeconds = (seconds % 60).toString().padStart(2, '0');
  return `${minutes}:${remainingSeconds}`;
}

function updateScreen() {
  timerElement.textContent = formatTime(timeLeft);
  document.title = `${formatTime(timeLeft)} — Pomodoro`;
  cyclesElement.textContent = cyclesCompleted;
  statusElement.textContent = timerId ? 'Concentre-se na tarefa atual.' : labels[currentMode];

  const total = durations[currentMode];
  const progress = ((total - timeLeft) / total) * 100;
  progressBar.style.width = `${progress}%`;

  startButton.textContent = timerId ? 'Pausar' : 'Iniciar';
}

function finishTimer() {
  clearInterval(timerId);
  timerId = null;

  if (currentMode === 'focus') {
    cyclesCompleted += 1;
    currentMode = cyclesCompleted % 4 === 0 ? 'longBreak' : 'shortBreak';
  } else {
    currentMode = 'focus';
  }

  timeLeft = durations[currentMode];
  updateActiveMode();
  updateScreen();
  playNotification();
}

function toggleTimer() {
  if (timerId) {
    clearInterval(timerId);
    timerId = null;
    updateScreen();
    return;
  }

  timerId = setInterval(() => {
    timeLeft -= 1;
    if (timeLeft <= 0) {
      finishTimer();
      return;
    }
    updateScreen();
  }, 1000);

  updateScreen();
}

function resetTimer() {
  clearInterval(timerId);
  timerId = null;
  timeLeft = durations[currentMode];
  updateScreen();
}

function updateActiveMode() {
  modeButtons.forEach((button) => {
    button.classList.toggle('active', button.dataset.mode === currentMode);
  });
}

function changeMode(event) {
  clearInterval(timerId);
  timerId = null;
  currentMode = event.currentTarget.dataset.mode;
  timeLeft = durations[currentMode];
  updateActiveMode();
  updateScreen();
}

function playNotification() {
  // O navegador pode bloquear áudio até que o usuário interaja com a página.
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification('Pomodoro concluído!', { body: 'Hora de trocar de atividade.' });
  }
}

startButton.addEventListener('click', toggleTimer);
resetButton.addEventListener('click', resetTimer);
modeButtons.forEach((button) => button.addEventListener('click', changeMode));

updateScreen();
