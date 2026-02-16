const { app, BrowserWindow, ipcMain, dialog } = require('electron');
const path = require('path');
const { spawn } = require('child_process');
const https = require('https');
const { autoUpdater } = require('electron-updater');
const fs = require('fs');

let mainWindow;
let phpServer;
const PORT = 8000;

// --- 1. CONFIGURATION ---
const isDev = !app.isPackaged;

// --- PATH LOGIC ---
const resourcesPath = isDev ? __dirname : process.resourcesPath;
const userDataPath = app.getPath('userData');
const appDbDir = path.join(userDataPath, 'databases');

const phpExec = path.join(resourcesPath, 'php', 'php.exe');
const backendRoot = path.join(resourcesPath, 'backend');
const webRoot = path.join(backendRoot, 'public');

const DATABASES = {
  core: {
    bundled: true,
    fileName: 'core.db',
    size: 15 * 1024 * 1024
  },
  versions: {
    bundled: false,
    fileName: 'versions.db',
    url: 'https://pub-bf0a74e2ba37417e92a1313629c26b3b.r2.dev/versions.db.zip',
    size: 150 * 1024 * 1024,
    compressed: true
  },
  commentaries: {
    bundled: false,
    fileName: 'commentaries.db',
    url: 'https://pub-bf0a74e2ba37417e92a1313629c26b3b.r2.dev/commentaries.db.zip',
    size: 180 * 1024 * 1024,
    compressed: true
  },
  extras: {
    bundled: true,
    fileName: 'extras.db',
    size: 16 * 1024 * 1024
  }
};

/**
 * Download a file from URL with progress tracking
 * Uses native Node.js https module - works in both dev and production
 */
async function downloadDatabase(url, destPath, isCompressed, onProgress, retryCount = 0) {
    console.log(`[DOWNLOAD] Starting: ${url} (Attempt ${retryCount + 1})`);
    
    return new Promise((resolve, reject) => {
        const tempPath = isCompressed ? destPath + '.zip' : destPath;
        const file = fs.createWriteStream(tempPath);
        
        let receivedBytes = 0;
        let totalBytes = 0;
        
        const request = https.get(url, {
            headers: { 'User-Agent': 'Lumina-Bible-App' }
        }, (response) => {
            // Handle redirects
            if (response.statusCode === 301 || response.statusCode === 302) {
                file.close();
                fs.unlinkSync(tempPath);
                return downloadDatabase(response.headers.location, destPath, isCompressed, onProgress, retryCount)
                    .then(resolve).catch(reject);
            }
            
            if (response.statusCode !== 200) {
                file.close();
                fs.unlinkSync(tempPath);
                return reject(new Error(`HTTP ${response.statusCode}`));
            }
            
            totalBytes = parseInt(response.headers['content-length'] || '0', 10);
            
            response.on('data', (chunk) => {
                receivedBytes += chunk.length;
                file.write(chunk);
                if (onProgress && totalBytes > 0) {
                    onProgress(receivedBytes, totalBytes, (receivedBytes / totalBytes) * 100);
                }
            });
            
            response.on('end', () => { file.end(); });
            
            file.on('finish', async () => {
                // ASYNCHRONOUS VERIFICATION
                const stats = fs.statSync(tempPath);
                if (totalBytes > 0 && stats.size !== totalBytes) {
                    console.error(`[VERIFY] Size mismatch! Expected ${totalBytes}, got ${stats.size}`);
                    fs.unlinkSync(tempPath);
                    if (retryCount < 2) {
                        return resolve(downloadDatabase(url, destPath, isCompressed, onProgress, retryCount + 1));
                    }
                    return reject(new Error("File verification failed after 3 attempts."));
                }

                if (isCompressed) {
                    try {
                        const AdmZip = require('adm-zip');
                        const zip = new AdmZip(tempPath);
                        zip.extractAllTo(path.dirname(destPath), true);
                        fs.unlinkSync(tempPath);
                        resolve();
                    } catch (zipError) {
                        fs.unlinkSync(tempPath);
                        if (retryCount < 2) {
                            return resolve(downloadDatabase(url, destPath, isCompressed, onProgress, retryCount + 1));
                        }
                        reject(new Error(`Extraction failed: ${zipError.message}`));
                    }
                } else {
                    resolve();
                }
            });
        });
        
        request.on('error', (e) => {
            file.close();
            if (fs.existsSync(tempPath)) fs.unlinkSync(tempPath);
            if (retryCount < 2) return resolve(downloadDatabase(url, destPath, isCompressed, onProgress, retryCount + 1));
            reject(e);
        });
        
        request.setTimeout(900000, () => {
            request.destroy();
            file.close();
            if (fs.existsSync(tempPath)) fs.unlinkSync(tempPath);
            if (retryCount < 2) return resolve(downloadDatabase(url, destPath, isCompressed, onProgress, retryCount + 1));
            reject(new Error("Download timeout."));
        });
    });
}

// --- LOGGING ---
const logFile = path.join(userDataPath, 'startup.log');
function log(msg) {
    const timestamp = new Date().toISOString();
    const entry = `[${timestamp}] ${msg}\n`;
    console.log(msg);
    try {
        if (!fs.existsSync(path.dirname(logFile))) fs.mkdirSync(path.dirname(logFile), { recursive: true });
        fs.appendFileSync(logFile, entry);
    } catch (e) {}
}

/**
 * Ensure all databases exist before starting the app
 */
async function ensureDatabases() {
    log('[DATABASE] Checking databases...');
    
    if (!fs.existsSync(appDbDir)) {
        fs.mkdirSync(appDbDir, { recursive: true });
        log(`[DATABASE] Created directory: ${appDbDir}`);
    }

    for (const [key, config] of Object.entries(DATABASES)) {
        const dbFilePath = path.join(appDbDir, config.fileName);
        
        if (fs.existsSync(dbFilePath)) {
            log(`[DATABASE] ✓ ${config.fileName} exists`);
            continue;
        }

        try {
            if (config.bundled) {
                const sourceDir = isDev ? path.join(resourcesPath, 'assets', 'data') : path.join(resourcesPath, 'databases');
                const sourcePath = path.join(sourceDir, config.fileName);
                log(`[DATABASE] Copying bundled: ${config.fileName} from ${sourcePath}`);
                
                if (!fs.existsSync(sourcePath)) throw new Error(`Bundled source missing: ${sourcePath}`);
                fs.copyFileSync(sourcePath, dbFilePath);
            } else {
                log(`[DATABASE] Downloading: ${config.fileName}`);
                if (mainWindow) mainWindow.webContents.send('download-status', { database: config.fileName, status: 'downloading' });
                
                await downloadDatabase(config.url, dbFilePath, config.compressed, (received, total, progress) => {
                    if (mainWindow) mainWindow.webContents.send('download-progress', { database: config.fileName, progress: progress.toFixed(1) });
                });
                if (mainWindow) mainWindow.webContents.send('download-status', { database: config.fileName, status: 'complete' });
            }
        } catch (error) {
            log(`[DATABASE] ✗ Error: ${error.message}`);
            dialog.showErrorBox("Database Error", `Failed to prepare ${config.fileName}: ${error.message}`);
            throw error;
        }
    }
}

/**
 * Start PHP Laravel backend server
 */
async function startPhpServer() {
    await ensureDatabases();

    if (!fs.existsSync(phpExec)) {
        log(`[PHP] ✗ Executable missing: ${phpExec}`);
        dialog.showErrorBox("Startup Error", `PHP missing at: ${phpExec}`);
        app.quit();
        return;
    }

    log(`[PHP] Starting on port ${PORT}...`);
    const env = { 
        ...process.env, 
        DB_CORE_PATH: path.join(appDbDir, DATABASES.core.fileName),
        DB_VERSIONS_PATH: path.join(appDbDir, DATABASES.versions.fileName),
        DB_COMMENTARIES_PATH: path.join(appDbDir, DATABASES.commentaries.fileName),
        DB_EXTRAS_PATH: path.join(appDbDir, DATABASES.extras.fileName),
        APP_KEY: 'base64:ANjMFKHklakCnLbxZd89SV8lIcQfyInk6l1rZV931cI=',
        APP_DEBUG: isDev ? 'true' : 'false',
        APP_STORAGE: userDataPath
    };
    
    phpServer = spawn(phpExec, ['-S', `127.0.0.1:${PORT}`, '-t', webRoot], { cwd: backendRoot, env: env });
    phpServer.on('error', (err) => log(`[PHP] ✗ Process error: ${err.message}`));
    log('[PHP] ✓ Server process spawned');
}

/**
 * Create main application window
 */
function createWindow() {
    log('[WINDOW] Creating window...');
    mainWindow = new BrowserWindow({
        width: 1280, height: 800,
        title: "Lumina",
        webPreferences: { nodeIntegration: true, contextIsolation: false },
        autoHideMenuBar: true,
        show: false
    });

    mainWindow.loadFile(path.join(__dirname, 'src', 'index.html'));
    mainWindow.once('ready-to-show', () => {
        mainWindow.show();
        log('[WINDOW] ✓ Window visible');
    });
}

// --- APP LIFECYCLE ---
app.on('ready', () => {
    log('--- App Starting v1.5.7 ---');
    
    // 1. Show window IMMEDIATELY so user sees progress
    createWindow();
    
    // 2. Start initialization in the background
    startPhpServer().catch(err => {
        log(`[APP] CRITICAL STARTUP FAILURE: ${err.message}`);
    });
});

app.on('window-all-closed', () => {
    console.log('[APP] All windows closed');
    if (process.platform !== 'darwin') {
        app.quit();
    }
});

app.on('will-quit', () => {
    console.log('[APP] Shutting down...');
    if (phpServer) {
        phpServer.kill();
        console.log('[PHP] Server stopped');
    }
});

app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
        createWindow();
    }
});

// Handle uncaught errors
process.on('uncaughtException', (error) => {
    console.error('[APP] Uncaught exception:', error);
});

process.on('unhandledRejection', (reason, promise) => {
    console.error('[APP] Unhandled rejection at:', promise, 'reason:', reason);
});