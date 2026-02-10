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
// Use the bundled PHP in both dev and production for consistency
const isDev = !app.isPackaged;
const phpExec = isDev 
    ? path.join(__dirname, 'php', 'php.exe') 
    : path.join(process.resourcesPath, 'php', 'php.exe');
const webRoot = isDev ? path.join(__dirname, 'backend', 'public') : path.join(process.resourcesPath, 'backend', 'public');
const dbPath = isDev 
    ? path.join(__dirname, 'assets', 'data', 'core.db') 
    : path.join(process.resourcesPath, 'assets', 'data', 'core.db');

function startPhpServer() {
    console.log(`Starting Laravel Backend on port ${PORT}...`);
    console.log(`Web Root: ${webRoot}`);

    // Pass necessary env vars to Laravel
    const env = { 
        ...process.env, 
        DB_CORE_PATH: dbPath,
        DB_VERSIONS_PATH: dbPath.replace('core.db', 'versions.db'),
        DB_COMMENTARIES_PATH: dbPath.replace('core.db', 'commentaries.db'),
        DB_EXTRAS_PATH: dbPath.replace('core.db', 'extras.db'),
        APP_KEY: 'base64:ANjMFKHklakCnLbxZd89SV8lIcQfyInk6l1rZV931cI=',
        APP_DEBUG: 'true'
    };
    
    phpServer = spawn(phpExec, ['-S', `127.0.0.1:${PORT}`, '-t', webRoot], { 
        cwd: path.join(__dirname, 'backend'),
        env: env 
    });

    phpServer.stdout.on('data', (data) => console.log(`PHP: ${data}`));
    phpServer.stderr.on('data', (data) => {
        const msg = data.toString();
        // Silence noisy access logs but keep real errors
        if (!msg.includes('Accepted') && !msg.includes('Closing') && !msg.includes('[200]:')) {
            console.error(`PHP Error: ${msg}`);
        }
    });
    phpServer.on('close', (code) => console.log(`PHP exited with code ${code}`));
}

// --- 2. MAIN WINDOW ---
function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1280, height: 800,
        title: "Lumina",
        webPreferences: {
            nodeIntegration: true, 
            contextIsolation: false
        },
        autoHideMenuBar: true
    });

    // Frontend is local HTML, Backend is local PHP server
    mainWindow.loadFile(path.join(__dirname, 'src', 'index.html'));
    
    if (!isDev) autoUpdater.checkForUpdatesAndNotify();
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
