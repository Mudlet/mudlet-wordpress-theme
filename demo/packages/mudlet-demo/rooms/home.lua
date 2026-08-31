-- The Front Page. The room the visitor lands in.

local SITE = require('mudlet-demo.site')
local dl = require('mudlet-demo.download')
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd
local spell, spellCap, thousands = core.spell, core.spellCap, core.thousands
local SCRIPT_LINES = core.SCRIPT_LINES
local someGames, currentBuild, press = dl.someGames, dl.currentBuild, dl.press

return {
    title = 'The Front Page',
    -- A function, not a string: the shelves hold however many worlds Mudlet
    -- currently ships, and the room is described after the seed lands.
    desc = function()
        return 'A wide room under a banner in letters the colour of a struck match: '
            .. 'play immersive, multiplayer, pure-text games. Below it, one orange '
            .. 'button, worn smooth in the middle. Shelves down the near wall hold '
            .. spell(SITE.games.count) .. ' boxed worlds. On a plinth in the centre '
            .. 'someone has left a terminal running a small MUD; you lean over it, '
            .. 'and lean over it, and lean over it.'
    end,
    exits = { north = 'news', down = 'vault', west = 'commons' },
    things = {
        {
            name = 'the banner',
            keys = { 'banner', 'letters', 'headline', 'sign' },
            look = function()
                say(C.text, 'Hand-set, and slightly uneven if you stand close. It says what the ',
                    'real front page says: the games are text, the text is multiplayer, ',
                    'and forty years in, that is still enough.')
                say(C.dim, 'It is also, word for word, ', link('the front page', URL.home), C.dim .. '.')
            end,
        },
        {
            name = 'the orange button',
            keys = { 'button', 'orange button', 'download button' },
            grab = press,
            look = function()
                say(C.text, 'Big. Orange. Worn smooth by a great many hands. It reads DOWNLOAD ',
                    'MUDLET, and it is not a metaphor for anything — it does that.')
                local build = currentBuild()
                if build then
                    say(C.dim, 'Pressing it downloads ', build.what,
                        ', which is the one for the machine you are reading this on.')
                end
                say(C.dim, 'You could ', cmd('press it', 'press button', 'press the button', C.dim),
                    C.dim, ', or go ', cmd('down', 'down', 'go down', C.dim),
                    C.dim, ' and take the crates one at a time.')
            end,
        },
        {
            name = 'the shelves',
            keys = { 'shelves', 'shelf', 'boxes', 'worlds', 'games', 'muds' },
            url = URL.download,
            look = function()
                local named = someGames(12)
                local rest = SITE.games.count - #named
                say(C.text, spellCap(SITE.games.count), ' boxes, a hostname stencilled on ',
                    'each lid: ', table.concat(named, ', '),
                    rest > 0 and (', and ' .. spell(rest) .. ' more.') or '.')
                say(C.dim, 'Mudlet ships with the lot. None of them will ask you for a port number.')
            end,
        },
        {
            name = 'a terminal on a plinth',
            keys = { 'terminal', 'plinth', 'screen', 'demo' },
            look = function()
                say(C.text, 'The screen shows a room description. The room is this one. In it, ',
                    'someone is leaning over a terminal.')
                say(C.text, 'You have found the demo. It is a real Mudlet — Lua, PCRE2, the ',
                    'lot — compiled to WebAssembly and running in this browser tab. ',
                    'Nothing is connected to anything. Every line you type is answered by ',
                    'a Lua package ', thousands(SCRIPT_LINES), ' lines long.')
                say(C.dim, 'Prove it: ',
                    cmd('lua echo("hello from Lua")', 'lua echo("hello from Lua")',
                        'run it in the demo\'s own Lua VM', C.dim))
            end,
        },
    },
}
