# .gitignore Configuration Guide

## Overview

This project uses a **single root-level `.gitignore`** file that covers all subprojects (Java, Bun/Node.js, etc.).

## Location

```
E:\perlinsos\splp\.gitignore
```

## What's Ignored

### 🏗️ Build Outputs
- `target/`, `build/`, `dist/`, `out/`, `bin/`
- Applies to all subdirectories

### 📦 Dependencies
- `node_modules/` (Node.js/Bun)
- `vendor/` (PHP/Composer)
- Maven wrapper files

### 💻 IDE Files
- **IntelliJ IDEA**: `.idea/`, `*.iml`, `*.iws`, `*.ipr`
- **Eclipse**: `.classpath`, `.project`, `.settings/`
- **VS Code**: `.vscode/`, `*.code-workspace`
- **NetBeans**: `nbproject/private/`

### 📝 Compiled & Package Files
- `*.class`, `*.jar`, `*.war`, `*.ear`
- `*.zip`, `*.tar.gz`, `*.rar`

### 📊 Logs & Coverage
- `*.log`, `logs/`
- `*.exec`, `jacoco.exec`, `coverage/`

### 🖥️ OS Files
- **macOS**: `.DS_Store`, `.Spotlight-V100`
- **Windows**: `Thumbs.db`, `Desktop.ini`
- **Linux**: `*~`

### 🔧 Tools & Scripts
- `apache-maven-*/` (Maven installation)
- `kill_service.ps1`, `run_service_fresh.ps1`

### 🔒 Sensitive Files
- `*.key`, `*.pem`, `*.p12`, `*.jks`, `*.keystore`
- `.env`, `.env.local`

## What's Tracked

### ✅ Always Tracked
- All source code (`.java`, `.ts`, `.js`, etc.)
- Configuration files (`pom.xml`, `package.json`)
- Documentation (`*.md` files)
- `ENCRYPTION_KEY.md` (explicitly included)

## Benefits of Root-Level .gitignore

1. **Single Source of Truth**: One file to maintain
2. **Consistent Rules**: Same patterns apply across all subprojects
3. **Easier Management**: No duplicate rules
4. **Better Organization**: Clearly sectioned by category

## Usage

### Check Ignored Files
```bash
git status --ignored
```

### Check What Would Be Ignored
```bash
git check-ignore -v <filename>
```

### Force Add an Ignored File (Not Recommended)
```bash
git add -f <filename>
```

## Current Git Status

After consolidation:
- ✅ All build outputs ignored
- ✅ All IDE files ignored
- ✅ All temporary files ignored
- ✅ Source code tracked
- ✅ Configuration tracked
- ✅ Documentation tracked

## Migration Notes

**Previous Setup:**
- Had separate `.gitignore` in `splp-java/`
- Duplicate rules between root and subdirectory

**Current Setup:**
- Single root `.gitignore`
- Removed `splp-java/.gitignore`
- All patterns consolidated and organized

## Maintenance

When adding new patterns:
1. Edit `E:\perlinsos\splp\.gitignore`
2. Add to appropriate section
3. Test with `git status`
4. Commit the change

## Notes

- Patterns with `*/` prefix apply to all subdirectories
- Patterns starting with `!` are exceptions (force include)
- More specific patterns override general ones
