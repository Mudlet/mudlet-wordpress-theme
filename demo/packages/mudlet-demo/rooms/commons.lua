-- The Commons, west of the front page: everywhere the site links out to.

local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd

return {
    title = 'The Commons',
    desc = 'Four doors and a cabinet, in a room that is otherwise all noticeboard. '
        .. 'None of the doors are locked. Two stand ajar, and you can hear the arguing '
        .. 'from here — amiably, about tabs. The cabinet is enormous and alphabetical.',
    exits = { east = 'home', north = 'workshop', west = 'makers' },
    things = {
        {
            name = 'the forum door',
            keys = { 'forum', 'forum door', 'forums' },
            url = URL.forum,
            look = function()
                say(C.text, 'Slow, thorough, searchable, twenty years deep. Somebody had your ',
                    'exact problem in 2013 and somebody else solved it underneath in 2014.')
                say(C.dim, '  ', link('forums.mudlet.org', URL.forum))
            end,
        },
        {
            name = 'the wiki door',
            keys = { 'wiki', 'wiki door', 'manual', 'docs' },
            url = URL.wiki,
            look = function()
                say(C.text, 'The manual. Every function, every event, every argument, with ',
                    'examples that run. This is the door people mean when they say Mudlet ',
                    'is approachable.')
                say(C.dim, '  ', link('wiki.mudlet.org', URL.wiki))
            end,
        },
        {
            name = 'the discord door',
            keys = { 'discord', 'discord door', 'chat' },
            url = URL.discord,
            look = function()
                say(C.text, 'Over five thousand people behind it. A dozen will answer a Lua ',
                    'question inside a minute, and a few of them wrote the function you are ',
                    'asking about.')
                say(C.dim, '  ', link('the invite', URL.discord))
            end,
        },
        {
            name = 'the workshop door',
            keys = { 'workshop', 'workshop door', 'github', 'source', 'contribute' },
            url = URL.github,
            look = function()
                say(C.text, 'C++ and Lua, GPL, and open to anyone who can be bothered. There are ',
                    'good first issues stacked on the desk by the door, and whoever reviews ',
                    'your patch has been doing this since 2008.')
                say(C.dim, 'It is also the one door here that goes somewhere: the workshop ',
                    'is ', cmd('north', 'north', 'go north', C.dim), C.dim, ', and there is ',
                    'somebody in it who knows what landed this week.')
                say(C.dim, '  ', link('github.com/Mudlet/Mudlet', URL.github))
            end,
        },
        {
            name = 'the cabinet',
            keys = { 'cabinet', 'packages', 'drawers' },
            url = URL.packages,
            look = function()
                say(C.text, '229 drawers from 123 authors: mappers, tabbed chat, curing systems, ',
                    'a keepalive pinger, and one that turns :) into an emoji. Mudlet ',
                    'installs any of them from its own command line.')
                say(C.dim, '  mpkg install carto        ', link('packages.mudlet.org', URL.packages))
            end,
        },
        {
            name = 'the noticeboard',
            keys = { 'noticeboard', 'names' },
            url = URL.makers,
            look = function()
                say(C.text, 'Names, mostly. A few dozen of them across a decade and change, ',
                    'pinned in no order anyone can explain and never taken down.')
                say(C.dim, 'The people themselves are through the door ',
                    cmd('west', 'west', 'go west', C.dim), C.dim, '. The roll of them: ',
                    link('the makers', URL.makers), C.dim .. '.')
            end,
        },
    },
}
