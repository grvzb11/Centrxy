// CERTIFY_WINDOW_CHROME_LIGHT_V1
// Keep the standard resizable Windows frame and window control buttons.
import { Menu as CertifyAppMenu, nativeTheme as CertifyNativeTheme } from 'electron';
if (process.platform === 'win32') {
    CertifyNativeTheme.themeSource = 'light';
    CertifyAppMenu.setApplicationMenu(null);
}
import NativePHP from '#plugin';
import { app, BrowserWindow } from 'electron';
import path from 'path';
import { createSplash } from './splash.js';
// Inherit User's PATH in Process & ChildProcess
import fixPath from 'fix-path';
fixPath();

// CERTIFY_PACKAGED_BUILD_PATH_FIX_V1
const buildPath = app.isPackaged
    ? path.join(process.resourcesPath, 'build')
    : path.resolve(import.meta.dirname, '../../../../vendor/nativephp/desktop/resources/build');
const defaultIcon = path.join(buildPath, 'icon.png');
const certificate = path.join(buildPath, 'cacert.pem');

const executable = process.platform === 'win32' ? 'php.exe' : 'php';
const phpBinary = path.join(buildPath, 'php', executable);
const appPath = path.join(buildPath, 'app');

let splashWindow;

app.whenReady().then(() => {
    try {
        splashWindow = createSplash(appPath, import.meta.dirname);
    } catch (error) {
        console.error('Error creating splash screen:', error);
    }

    NativePHP.bootstrap(app, defaultIcon, phpBinary, certificate, appPath);
});

app.on('browser-window-created', (event, window) => {
    if (splashWindow && window !== splashWindow) {
        window.webContents.on('did-navigate', (evt, url) => {
            if (url.startsWith('http://127.0.0.1') || url.startsWith('http://localhost')) {
                if (splashWindow) {
                    splashWindow.close();
                    splashWindow = null;
                }
            }
        });
    }
});

// CERTIFY_DESKTOP_DEV_WINDOW_ICON_V1
// Set the Windows development window icon without modifying Electron's installed binary.
// Production builds continue to use public/icon.png and public/icon.ico via NativePHP.
import { app as certifyIconApp } from 'electron';
import { existsSync as certifyIconExists } from 'node:fs';
import { resolve as certifyIconResolve } from 'node:path';

if (process.platform === 'win32' && !certifyIconApp.isPackaged) {
  const certifyIconCandidates = [
    certifyIconResolve(process.cwd(), '..', '..', 'public', 'icon.ico'),
    certifyIconResolve(process.cwd(), 'public', 'icon.ico'),
  ];
  const certifyDevWindowIcon = certifyIconCandidates.find(certifyIconExists);
  if (certifyDevWindowIcon) {
    certifyIconApp.on('browser-window-created', (_event, certifyWindow) => {
      try {
        certifyWindow.setIcon(certifyDevWindowIcon);
      } catch (error) {
        console.warn('[Certify] Unable to apply development window icon:', error);
      }
    });
  } else {
    console.warn('[Certify] Development window icon not found in public/icon.ico');
  }
}
