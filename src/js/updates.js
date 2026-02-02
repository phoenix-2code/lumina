// --- Auto-Update Renderer Logic ---
const { ipcRenderer } = require('electron');

const banner = document.getElementById('update-banner');
const msg = document.getElementById('update-msg');
const restartBtn = document.getElementById('restart-btn');

ipcRenderer.on('update_available', () => {
    ipcRenderer.removeAllListeners('update_available');
    msg.innerText = 'New update available. Downloading...';
    banner.style.display = 'flex';
});

ipcRenderer.on('update_downloaded', () => {
    ipcRenderer.removeAllListeners('update_downloaded');
    msg.innerText = 'Update downloaded. It will be installed on restart.';
    restartBtn.style.display = 'block';
    banner.style.display = 'flex';
});

function restartApp() {
    ipcRenderer.send('restart_app');
}
