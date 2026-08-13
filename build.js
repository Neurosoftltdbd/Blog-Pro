// build.js - Production zip builder (Tailwind already built by npm script)

const { execSync } = require("child_process");
const fs = require("fs");
const path = require("path");
const os = require("os");

function log(section) {
  console.log(`\n=== ${section} ===`);
}

const zipName = `Blog-pro.zip`;
// Create zip in the parent directory of the theme
const zipPath = path.join(path.dirname(__dirname), zipName);

// 1. Bump the version number in style.css before building
const styleCssPath = path.join(__dirname, "style.css");

function bumpVersion(filePath) {
  let content = fs.readFileSync(filePath, "utf8");

  const versionRegex = /(Version:\s*)(\d+)\.(\d+)\.(\d+)/i;
  const match = content.match(versionRegex);

  if (!match) {
    console.warn(
      "No 'Version: x.y.z' line found in style.css — skipping bump.",
    );
    return null;
  }

  const [, prefix, major, minor, patch] = match;
  const newVersion = `${major}.${minor}.${Number(patch) + 1}`;
  content = content.replace(versionRegex, `${prefix}${newVersion}`);

  fs.writeFileSync(filePath, content, "utf8");
  return newVersion;
}

log("Bumping theme version");
const newVersion = bumpVersion(styleCssPath);
if (newVersion) console.log(`New version: ${newVersion}`);

// 2. Create a temporary folder for the clean copy
const tempDir = path.join(os.tmpdir(), `blogpro-build-${Date.now()}`);
if (fs.existsSync(tempDir))
  fs.rmSync(tempDir, { recursive: true, force: true });
fs.mkdirSync(tempDir, { recursive: true });

// 3. Define items that must be excluded from the archive
const excludeSet = new Set([
  "node_modules",
  "build.js",
  "package-lock.json",
  "package.json",
  "input.css",
  "tailwind.config.js",
  ".gitignore",
  "readme.md",
  "build.js",
  ".git",
  ".vscode",
  ".claude",
]);

function copyRecursive(src, dest) {
  const stats = fs.statSync(src);
  const base = path.basename(src);
  if (excludeSet.has(base)) return; // Skip excluded top‑level entries
  if (stats.isDirectory()) {
    fs.mkdirSync(dest, { recursive: true });
    for (const entry of fs.readdirSync(src)) {
      copyRecursive(path.join(src, entry), path.join(dest, entry));
    }
  } else {
    fs.copyFileSync(src, dest);
  }
}

log("Copying theme files into temporary folder");
copyRecursive(__dirname, tempDir);

// 4. Create the zip archive with files at the root (no wrapping folder)
log("Creating zip archive");
try {
  const zipCommand = `powershell -NoProfile -Command "Set-Location -LiteralPath '${tempDir}'; Compress-Archive -Path * -DestinationPath '${zipPath}' -Force"`;
  execSync(zipCommand, { stdio: "inherit" });
} catch (err) {
  console.error("Zip creation failed:", err);
  process.exit(1);
}

// 5. Clean up the temporary folder
fs.rmSync(tempDir, { recursive: true, force: true });

log("Build completed successfully");
console.log(`Generated: ${zipPath}`);
