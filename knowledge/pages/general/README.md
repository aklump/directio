# Directio

Directio is a tool to track task completion across time.

It was built for the following scenario, but surely will find other use cases as it evolves and gets used by others.

Every so often our website needs to be updated. We have a number of master markdown files that lead us through the update process for any website. Some of the tasks are recurring, some are one-time and some should be completed at regular intervals.

When these markdown files are applied to a single website, the one-time tasks should be hidden once they are done, as this presents a smaller document to review. Directio will track all tasks you complete and hide those each time you want to perform a new update session.

## Quick Start

### Master File Setup

1. Locate your master files outside of your website project.
2. Create at least one master file, e.g. _/foo/bar/document.md_
3. Add [Directio syntax](@syntax) to that document.

### Project Usage

1. In the shell, cd to your project root.
2. `./vendor/bin/directio new /foo/bar/document.md`
3. Answer that you want to initialize your project.
4. Open the created document and mark each task complete.
5. Use `./vendor/bin/directio update` to flush the completed tasks from the file.

### Next Time

1. In the shell, cd to your project root.
2. `./vendor/bin/directio new /foo/bar/document.md`
3. Open the created document and notice all completed tasks have been removed.
4. Continue as before marking complete and updating.
