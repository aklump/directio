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

## Documentation

<div style="display:flex;align-items:end">
<svg width="48" height="48" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path fill="#fff" d="M0 0h24v24H0z"/><path d="M12 6.91c-1.1-1.401-2.796-2.801-6.999-2.904A.491.491 0 0 0 4.5 4.5v12.097c0 .276.225.493.501.502 4.203.137 5.899 2 6.999 3.401m0-13.59c1.1-1.401 2.796-2.801 6.999-2.904a.487.487 0 0 1 .501.489v12.101a.51.51 0 0 1-.501.503c-4.203.137-5.899 2-6.999 3.401m0-13.59V20.5" stroke="#000" stroke-linejoin="round"/><path d="M19.235 6H21.5a.5.5 0 0 1 .5.5v13.039c0 .405-.477.673-.846.51-.796-.354-2.122-.786-3.86-.786C14.353 19.263 12 21 12 21s-2.353-1.737-5.294-1.737c-1.738 0-3.064.432-3.86.785-.37.164-.846-.104-.846-.509V6.5a.5.5 0 0 1 .5-.5h2.265" stroke="#000" stroke-linejoin="round"/></svg>&nbsp;&nbsp;
[Read the online documentation](https://aklump.github.io/directio/pages/general/readme.html) &rarr;
</div>
