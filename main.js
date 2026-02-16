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

/**
 * Ensure all databases exist before starting the app
 */
async function ensureDatabases() {
    console.log('[DATABASE] Checking databases...');
    
    // 1. Create main user data directory if it doesn't exist
    if (!fs.existsSync(userDataPath)) {
        fs.mkdirSync(userDataPath, { recursive: true });
        console.log(`[DATABASE] Created user data directory: ${userDataPath}`);
    }

    // 2. Create database directory
    if (!fs.existsSync(appDbDir)) {
        fs.mkdirSync(appDbDir, { recursive: true });
        console.log(`[DATABASE] Created directory: ${appDbDir}`);
    }

    // Process each database
    for (const [key, config] of Object.entries(DATABASES)) {
        const dbFilePath = path.join(appDbDir, config.fileName);
        
        // Skip if already exists
        if (fs.existsSync(dbFilePath)) {
            const sizeMB = (fs.statSync(dbFilePath).size / 1024 / 1024).toFixed(2);
            console.log(`[DATABASE] ✓ ${config.fileName} exists (${sizeMB} MB)`);
            continue;
        }

        try {
            if (config.bundled) {
                // Copy from bundled resources
                // In dev, use assets/data. In prod, use databases folder (as defined in package.json extraResources)
                const sourceDir = isDev ? path.join(resourcesPath, 'assets', 'data') : path.join(resourcesPath, 'databases');
                const sourcePath = path.join(sourceDir, config.fileName);
                console.log(`[DATABASE] Copying bundled: ${config.fileName}`);
                console.log(`[DATABASE] Source: ${sourcePath}`);
                
                if (!fs.existsSync(sourcePath)) {
                    throw new Error(`Bundled database not found: ${sourcePath}`);
                }
                
                fs.copyFileSync(sourcePath, dbFilePath);
                const sizeMB = (fs.statSync(dbFilePath).size / 1024 / 1024).toFixed(2);
                console.log(`[DATABASE] ✓ Copied ${config.fileName} (${sizeMB} MB)`);
                
            } else {
                // Download from internet
                console.log(`[DATABASE] Downloading: ${config.fileName}`);
                
                // Notify UI
                if (mainWindow && mainWindow.webContents) {
                    mainWindow.webContents.send('download-status', {
                        database: config.fileName,
                        status: 'downloading',
                        progress: 0
                    });
                }
                
                await downloadDatabase(
                    config.url, 
                    dbFilePath, 
                    config.compressed, 
                    (received, total, progress) => {
                        // Update UI with progress
                        if (mainWindow && mainWindow.webContents) {
                            mainWindow.webContents.send('download-progress', {
                                database: config.fileName,
                                received: received,
                                total: total,
                                progress: progress.toFixed(1)
                            });
                        }
                        
                        // Log progress every 10%
                        if (Math.floor(progress) % 10 === 0) {
                            console.log(`[DATABASE] ${config.fileName}: ${progress.toFixed(1)}%`);
                        }
                    }
                );
                
                // Notify UI of completion
                if (mainWindow && mainWindow.webContents) {
                    mainWindow.webContents.send('download-status', {
                        database: config.fileName,
                        status: 'complete'
                    });
                }
                
                const sizeMB = (fs.statSync(dbFilePath).size / 1024 / 1024).toFixed(2);
                console.log(`[DATABASE] ✓ Downloaded ${config.fileName} (${sizeMB} MB)`);
            }
        } catch (error) {
            console.error(`[DATABASE] ✗ Failed to prepare ${config.fileName}:`, error);
            
            // Show error dialog
            dialog.showErrorBox(
                "Database Error", 
                `Failed to prepare database: ${config.fileName}\n\n${error.message}\n\nPlease check your internet connection and try again.`
            );
            
            // Clean up partial downloads
            if (fs.existsSync(dbFilePath)) {
                fs.unlinkSync(dbFilePath);
            }
            if (fs.existsSync(dbFilePath + '.zip')) {
                fs.unlinkSync(dbFilePath + '.zip');
            }
            
            throw error;
        }
    }

    console.log('[DATABASE] ✓ All databases ready');
}

/**
 * Start PHP Laravel backend server
 */
async function startPhpServer() {
    // Ensure Laravel storage directories exist in userData
    const storageDirs = [
        path.join(userDataPath, 'storage', 'logs'),
        path.join(userDataPath, 'storage', 'framework', 'views'),
        path.join(userDataPath, 'storage', 'framework', 'sessions'),
        path.join(userDataPath, 'storage', 'framework', 'cache')
    ];
    
    storageDirs.forEach(dir => {
        if (!fs.existsSync(dir)) {
            fs.mkdirSync(dir, { recursive: true });
            console.log(`[PHP] Created storage directory: ${dir}`);
        }
    });

    // Wait for databases to be ready
    try {
        await ensureDatabases();
    } catch (error) {
        console.error('[PHP] Cannot start - database setup failed');
        app.quit();
        return;
    }

    // Verify PHP exists
    if (!fs.existsSync(phpExec)) {
        console.error(`[PHP] ✗ PHP not found: ${phpExec}`);
        dialog.showErrorBox(
            "Startup Error", 
            `Required component (PHP) missing at:\n${phpExec}\n\nPlease reinstall Lumina.`
        );
        app.quit();
        return;
    }

    console.log(`[PHP] Starting Laravel backend on port ${PORT}...`);
    console.log(`[PHP] Executable: ${phpExec}`);
    console.log(`[PHP] Backend root: ${backendRoot}`);
    console.log(`[PHP] Web root: ${webRoot}`);

    // Environment variables for Laravel
    const env = { 
        ...process.env, 
        DB_CORE_PATH: path.join(appDbDir, DATABASES.core.fileName),
        DB_VERSIONS_PATH: path.join(appDbDir, DATABASES.versions.fileName),
        DB_COMMENTARIES_PATH: path.join(appDbDir, DATABASES.commentaries.fileName),
        DB_EXTRAS_PATH: path.join(appDbDir, DATABASES.extras.fileName),
        APP_KEY: 'base64:ANjMFKHklakCnLbxZd89SV8lIcQfyInk6l1rZV931cI=',
        APP_DEBUG: isDev ? 'true' : 'false',
        APP_CONFIG_CACHE: path.join(userDataPath, 'config.php'),
        APP_STORAGE: userDataPath
    };
    
    // Start PHP server
    phpServer = spawn(phpExec, ['-S', `127.0.0.1:${PORT}`, '-t', webRoot], { 
        cwd: backendRoot,
        env: env 
    });

    phpServer.stdout.on('data', (data) => {
        console.log(`[PHP] ${data.toString().trim()}`);
    });
    
    phpServer.stderr.on('data', (data) => {
        const msg = data.toString();
        // Filter out noisy access logs
        if (!msg.includes('Accepted') && 
            !msg.includes('Closing') && 
            !msg.includes('[200]:') &&
            !msg.includes('Development Server')) {
            console.error(`[PHP] ${msg.trim()}`);
        }
    });
    
    phpServer.on('close', (code) => {
        console.log(`[PHP] Server exited with code ${code}`);
    });
    
    phpServer.on('error', (error) => {
        console.error(`[PHP] Failed to start:`, error);
    });
    
    console.log('[PHP] ✓ Server started');
}

/**
 * Create main application window
 */
function createWindow() {
    console.log('[WINDOW] Creating main window...');
    
    mainWindow = new BrowserWindow({
        width: 1280, 
        height: 800,
        title: "Lumina",
        webPreferences: {
            nodeIntegration: true, 
            contextIsolation: false
        },
        autoHideMenuBar: true,
        show: false // Don't show until ready
    });

    // Load frontend
    mainWindow.loadFile(path.join(__dirname, 'src', 'index.html'));
    
    // Show window when ready
    mainWindow.once('ready-to-show', () => {
        mainWindow.show();
        console.log('[WINDOW] ✓ Window shown');
    });
    
    // Check for updates (production only)
    if (!isDev) {
        setTimeout(() => {
            autoUpdater.checkForUpdatesAndNotify();
        }, 5000); // Wait 5 seconds before checking
    }
    
    console.log('[WINDOW] ✓ Window created');
}

// --- AUTO-UPDATER EVENTS ---
autoUpdater.on('update-available', (info) => {
    console.log('[UPDATER] Update available:', info.version);
    if (mainWindow && mainWindow.webContents) {
        mainWindow.webContents.send('update_available', info);
    }
});

autoUpdater.on('update-downloaded', (info) => {
    console.log('[UPDATER] Update downloaded:', info.version);
    if (mainWindow && mainWindow.webContents) {
        mainWindow.webContents.send('update_downloaded', info);
    }
});

autoUpdater.on('error', (error) => {
    console.error('[UPDATER] Error:', error);
});

ipcMain.on('restart_app', () => {
    console.log('[UPDATER] Restarting to install update...');
    autoUpdater.quitAndInstall();
});

// --- APP LIFECYCLE ---
app.on('ready', async () => {
    console.log('[APP] Starting Lumina v1.5.6...');
    console.log('[APP] Mode:', isDev ? 'DEVELOPMENT' : 'PRODUCTION');
    console.log('[APP] Resources:', resourcesPath);
    console.log('[APP] User data:', userDataPath);
    console.log('[APP] DB Directory:', appDbDir);
    
    // 1. First ensure databases are ready
    try {
        // We call startPhpServer which internally calls ensureDatabases
        await startPhpServer();
    } catch (startupError) {
        console.error('[APP] Startup failed:', startupError);
        // Error dialog is shown inside startPhpServer/ensureDatabases
        return;
    }

    // 2. Finally create the window
    createWindow();
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