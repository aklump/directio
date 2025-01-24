# Directio

![Directio](images/directio.jpg)

> Directio is a tool to track tasks over time.

Projects require regular updates. There can be lots of steps to remember, so we write a list of tasks. And then we follow the list each time we do an update. Just to be sure we miss nothing.

At the start of each update, one makes a duplicate of the tasklist, e.g. _today.md_. Scanning through _today.md_ one notices that some of the tasks have already been done, and are by nature non-recurring. Others recur, but sometimes at different intervals. It's tedius to have to follow along, when not all of the tasks apply to this moment. What if the duplicate somehow KNEW what didn't need to be done today, i.e., what was already completed, or what doesn't need to be done quite yet. What if those tasks were removed from the working file? **Hello Directio.**

## Quick Start

### Tasklist File Setup

1. Locate your tasklist file(s) outside of your project, this assumes you want the same tasklist for multiple projects.
2. Create at least one tasklist, e.g. _/foo/bar/document.md_
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
