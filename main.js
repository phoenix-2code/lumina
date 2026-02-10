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
const webRoot = isDev ? path.join(__dirname, 'src') : path.join(process.resourcesPath, 'src');
const dbPath = isDev 
    ? path.join(__dirname, 'assets', 'bible_app.db') 
    : path.join(process.resourcesPath, 'assets', 'bible_app.db');

function startPhpServer() {
    console.log(`Starting PHP Server on port ${PORT}...`);
    console.log(`Web Root: ${webRoot}`);
    console.log(`DB Path: ${dbPath}`);

    // Pass the DB path as an environment variable to the PHP process
    const env = { ...process.env, BIBLE_DB_PATH: dbPath };
    
    // Serve the 'src' directory as the root
    phpServer = spawn(phpExec, ['-S', `127.0.0.1:${PORT}`, '-t', webRoot], { 
        cwd: webRoot,
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
