const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const { spawn } = require('child_process');
const http = require('http');
const { autoUpdater } = require('electron-updater');

let mainWindow;
let phpServer;
const PORT = 8000;

// --- 1. CONFIGURATION ---
// In production (bundled), PHP is in resources/php/php.exe
// In development, we use the system 'php' command
const isDev = !app.isPackaged;
const phpExec = isDev ? 'php' : path.join(process.resourcesPath, 'php', 'php.exe');
const webRoot = isDev ? path.join(__dirname, 'src') : path.join(process.resourcesPath, 'src');

function startPhpServer() {
    console.log(`Starting PHP Server on port ${PORT}...`);
    // Serve the 'src' directory as the root
    phpServer = spawn(phpExec, ['-S', `127.0.0.1:${PORT}`, '-t', webRoot], { cwd: webRoot });
    phpServer.on('close', (code) => console.log(`PHP exited with code ${code}`));
}

// --- 2. MAIN WINDOW ---
function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1280, height: 800,
        title: "FaithStream",
        webPreferences: {
            nodeIntegration: true, // Needed for IPC communication for updates
            contextIsolation: false
        },
        autoHideMenuBar: true
    });

    const checkServer = () => {
        http.get(`http://127.0.0.1:${PORT}/index.html`, (res) => {
            if (res.statusCode === 200) {
                mainWindow.loadURL(`http://127.0.0.1:${PORT}/index.html`);
                if (!isDev) autoUpdater.checkForUpdatesAndNotify();
            } else { setTimeout(checkServer, 200); }
        }).on('error', () => setTimeout(checkServer, 200));
    };
    checkServer();
}

// --- 3. AUTO-UPDATER EVENTS ---
autoUpdater.on('update-available', () => {
    mainWindow.webContents.send('update_available');
});

autoUpdater.on('update-downloaded', () => {
    mainWindow.webContents.send('update_downloaded');
});

ipcMain.on('restart_app', () => {
    autoUpdater.quitAndInstall();
});

// --- 4. APP LIFECYCLE ---
app.on('ready', () => {
    startPhpServer();
    createWindow();
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') app.quit();
});

app.on('will-quit', () => {
    if (phpServer) phpServer.kill();
});
