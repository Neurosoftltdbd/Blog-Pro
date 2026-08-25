// build.js - Production zip builder (Tailwind already built by npm script)

const { execSync } = require("child_process");
const fs = require("fs");
const path = require("path");
const os = require("os");

function log(section) {
  console.log(`\n=== ${section} ===`);
}

const zipName = `blog-pro.zip`;
// Create zip in the parent directory of the theme
const zipPath = path.join(path.dirname(__dirname), zipName);

// 0. Validate required production files exist
log("Validating required files");
const requiredFiles = ["style.css", "functions.php", "index.php"];
for (const f of requiredFiles) {
  if (!fs.existsSync(path.join(__dirname, f))) {
    console.error(`Required file missing: ${f}`);
    process.exit(1);
  }
}
const compiledCss = path.join(__dirname, "assets", "css", "tailwind.css");
if (!fs.existsSync(compiledCss)) {
  console.error(
    "assets/css/tailwind.css missing — run the Tailwind build first (npm run build)",
  );
  process.exit(1);
}
if (!fs.existsSync(path.join(__dirname, "readme.txt"))) {
  console.warn("readme.txt not found — required for WordPress.org submission");
}

// 1. Bump the version number in style.css before building
const styleCssPath = path.join(__dirname, "style.css");
const styleCssOriginal = fs.readFileSync(styleCssPath, "utf8");

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
  ".git",
  ".vscode",
  ".claude",
  ".agents",
  "Thumbs.db",
  ".DS_Store",
  "*.log",
  "skills-lock.json",
  ".gitignore",
  ".htaccess-blogpro",
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
// Always wrap in lowercase 'blog-pro/' — WordPress theme folders must be
// lowercase; the dev folder is 'Blog-Pro' (case-sensitive servers break).
const themeDir = path.join(tempDir, "blog-pro");
fs.mkdirSync(themeDir, { recursive: true });
copyRecursive(__dirname, themeDir);

// 4. Create the zip archive (files under blog-pro/ wrapping folder)
log("Creating zip archive");
try {
  const zipCommand = `powershell -NoProfile -Command "Set-Location -LiteralPath '${tempDir}'; Compress-Archive -Path '${themeDir}' -DestinationPath '${zipPath}' -Force"`;
  execSync(zipCommand, { stdio: "inherit" });
} catch (err) {
  console.error("Zip creation failed:", err);
  // Restore style.css if version bump already happened
  fs.writeFileSync(styleCssPath, styleCssOriginal, "utf8");
  fs.rmSync(tempDir, { recursive: true, force: true });
  process.exit(1);
}

// 5. Clean up the temporary folder
fs.rmSync(tempDir, { recursive: true, force: true });

log("Build completed successfully");
console.log(`Generated: ${zipPath}`);
