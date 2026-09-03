-- The Release Vault, down the stairs: /download/, one crate per platform.

local SITE = require('mudlet-demo.site')
local dl = require('mudlet-demo.download')
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd
local crateLines = dl.crateLines

return {
    title = 'The Release Vault',
    desc = function()
        return 'Cold, dry, very well swept. Four crates stand on trestles, each stencilled '
            .. 'with a platform and a weight, each with a long number chalked on the lid '
            .. 'that nobody has ever checked. A fifth stands apart by the stairs, its lid '
            .. 'loose and its contents faintly warm. On the wall, in chalk: '
            .. SITE.release.version .. ' — ' .. SITE.release.date_loud .. '.'
    end,
    -- Down again: the cellar under this one is the package itself, in crates.
    -- See rooms/cellar.lua.
    exits = { up = 'home', down = 'cellar' },
    things = {
        {
            name = 'windows',
            keys = { 'windows', 'win', 'exe' },
            url = URL.win,
            crate = 'windows',
            look = function()
                say(C.text, 'Pine, sealed ', SITE.release.date, '. An installer, signed — ',
                    'the certificate was donated, which is the sort of thing that happens ',
                    'to projects people like.')
                crateLines('windows')
            end,
        },
        {
            name = 'macos',
            keys = { 'macos', 'mac', 'intel', 'x86', 'x86_64' },
            url = URL.macx,
            crate = 'macos',
            look = function()
                say(C.text, 'The older of the two Macs — Intel, x86_64, sealed the same ',
                    'morning as the rest of them.')
                crateLines('macos')
            end,
        },
        {
            name = 'silicon',
            keys = { 'silicon', 'apple silicon', 'arm', 'arm64', 'm1', 'm2' },
            url = URL.macarm,
            crate = 'silicon',
            look = function()
                say(C.text, 'Apple Silicon, built native — no translation layer, no apology on ',
                    'startup.')
                crateLines('silicon')
            end,
        },
        {
            name = 'linux',
            keys = { 'linux', 'appimage', 'ubuntu', 'debian' },
            url = URL.linux,
            crate = 'linux',
            look = function()
                say(C.text, 'The one that is not really an installer: an AppImage. Put it ',
                    'somewhere permanent and run it from there. It is the Ubuntu answer, ',
                    'the Debian answer and the "my distribution is unusual" answer, all ',
                    'under one lid.')
                crateLines('linux')
            end,
        },
        {
            name = 'ptb',
            keys = { 'ptb', 'fifth', 'fifth crate', 'warm crate', 'test', 'public test build' },
            url = URL.ptb,
            heavy = 'the Public Test Build snapshots',
            look = function()
                say(C.text, 'The Public Test Build: everything that has landed since ',
                    SITE.release.version, ', unsealed by design. The people who open this ',
                    'crate are the reason the other four are safe to open.')
                say(C.dim, '  ', link('Public Test Build snapshots', URL.ptb))
            end,
        },
        {
            name = 'the chalk mark',
            keys = { 'chalk', 'wall', 'older', 'mark', 'trestles' },
            url = URL.download,
            look = function()
                say(C.text, 'Under the chalk mark, faintly, are all the chalk marks before it — ',
                    'every version back to the early ones, still on the shelf, still ',
                    'downloadable by anyone with a reason.')
                say(C.dim, 'The full list: ', link('mudlet.org/download', URL.download), C.dim .. '.')
            end,
        },
    },
}
