# Project Screenshots & Visual Assets

## Purpose
This directory contains visual assets accessible to all team members for collaboration, feedback, and documentation.

## Usage

### Adding Screenshots
```bash
# Copy from personal screenshots
cp "~/Screenshots/your-screenshot.png" assets/screenshots/descriptive-name.png

# Or use the provided script
./scripts/add-screenshot.sh "Feature Name" path/to/screenshot.png
```

### Naming Convention
- `feature-name-state.png` - For feature demonstrations
- `bug-reproduction-YYYY-MM-DD.png` - For bug reports
- `mockup-component-version.png` - For design mockups
- `workflow-step-N.png` - For process documentation

### Referencing in Documentation
```markdown
![Feature Preview](../assets/screenshots/your-image.png)
```

### Team Collaboration
1. Add screenshots here instead of external platforms
2. Reference in issues, PRs, and documentation
3. Use descriptive commit messages when adding images
4. Consider file size - optimize large images

## Current Assets
<!-- Auto-generated list - update when adding files -->

---
*For detailed workflow, see: [docs/team-resources/image-sharing-guide.md](../docs/team-resources/image-sharing-guide.md)*