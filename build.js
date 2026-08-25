// build.js - Production zip builder (Tailwind already built by npm script)

const { execSync } = require("child_process");
const fs = require("fs");
const path = require("path");
const os = require("os");

function log(section) {
  console.log(`\n=== ${section} ===`);
}

const zipName = `blog-pro.zip`;

// Any leftover build-staging folder (.blogpro-build-*) inside the theme must
// never be copied — if a previous run crashed before cleanup, copying it
// nests it one level deeper on every subsequent build (infinite recursion).
const excludePatterns = [/^\.blogpro-build-/];

function rmDeep(p) {
  // Long-path (\\?\) form so Windows can delete trees deeper than MAX_PATH.
  const lp = p.startsWith("\\\\?\\") ? p : "\\\\?\\" + path.resolve(p);
  fs.rmSync(lp, { recursive: true, force: true });
}

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

// 1b. Sweep out any leftover staging folder from a previous crashed build
// (it would otherwise be copied into the zip recursively).
for (const entry of fs.readdirSync(__dirname)) {
  if (excludePatterns.some((re) => re.test(entry))) {
    console.warn(`Removing leftover build staging folder: ${entry}`);
    rmDeep(path.join(__dirname, entry));
  }
}

// 2. Create a temporary folder for the clean copy — OUTSIDE the theme dir
// (a temp dir inside the theme would be re-copied recursively into itself,
// causing infinite nesting). Use os.tmpdir() and normalize to the long
// path form so PowerShell's enumerated FullName matches the substring base.
const tempDirShort = path.join(os.tmpdir(), `blogpro-build-${Date.now()}`);
if (fs.existsSync(tempDirShort))
  rmDeep(tempDirShort);
fs.mkdirSync(tempDirShort, { recursive: true });
// Resolve to long form (os.tmpdir() may return 8.3 short names like
// RHYTHM~1 which mismatch the long names PowerShell enumerates).
const tempDir = fs.realpathSync(tempDirShort);

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
  if (excludePatterns.some((re) => re.test(base)))
    return; // Skip leftover build-staging folders at any depth
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

// 4. Create the zip archive (files under blog-pro/ wrapping folder).
// Compress-Archive / tar include empty directory entries, which WordPress's
// theme uploader rejects ("Could not copy file. blog-pro\assets\"). Build
// the zip via System.IO.Compression with file entries only.
log("Creating zip archive");
try {
  // Remove any previous zip first — ZipFile.Open with 'Create' throws
  // IOException ("file already exists") otherwise.
  fs.rmSync(zipPath, { force: true });
  // Write the zip script to a temp .ps1 (avoids shell-escaping issues),
  // then execute it. Zip contains FILE entries only — no empty directory
  // entries, which WordPress's theme uploader rejects.
  const zipScript = path.join(tempDir, "make-zip.ps1");
  fs.writeFileSync(
    zipScript,
    [
      "param($zipPath, $tempDir)",
      "Add-Type -AssemblyName System.IO.Compression.FileSystem",
      "$zip = [System.IO.Compression.ZipFile]::Open($zipPath, 'Create')",
      "$src = Join-Path $tempDir 'blog-pro'",
      "$tempFull = [System.IO.Path]::GetFullPath($tempDir)",
      "Get-ChildItem -LiteralPath $src -Recurse -File | ForEach-Object {",
      "  $full = [System.IO.Path]::GetFullPath($_.FullName)",
      "  $rel = $full.Substring($tempFull.Length + 1).Replace('\\', '/')",
      "  [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $_.FullName, $rel) | Out-Null",
      "}",
      "$zip.Dispose()",
      "",
    ].join("\n")
  );
  const cmd = `powershell -NoProfile -ExecutionPolicy Bypass -File "${zipScript}" -zipPath "${zipPath}" -tempDir "${tempDir}"`;
  execSync(cmd, { stdio: "inherit" });
  // PowerShell doesn't propagate exceptions as exit codes — verify the zip
  // actually exists and is non-empty, or treat it as a failure.
  if (!fs.existsSync(zipPath) || fs.statSync(zipPath).size === 0) {
    throw new Error("Zip script ran but produced no archive");
  }
} catch (err) {
  console.error("Zip creation failed:", err);
  // Restore style.css if version bump already happened
  fs.writeFileSync(styleCssPath, styleCssOriginal, "utf8");
  rmDeep(tempDir);
  process.exit(1);
}

// 5. Clean up the temporary folder
rmDeep(tempDir);

log("Build completed successfully");
console.log(`Generated: ${zipPath}`);
