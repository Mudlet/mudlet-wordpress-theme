-- The Stacks, south of the commons and behind the wiki door: the manual's index
-- with walls round it. One box per name, and an imp on a ladder who will not
-- hand over a box for anything but the name on its lid — which is what makes
-- this the room the visitor writes an alias in. Both lists it answers out of,
-- and everything it says, are in mudlet-demo/catalogue.lua.

local D = demo
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local cat = require('mudlet-demo.catalogue')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd
local thousands = core.thousands

return {
    title = 'The Stacks',
    -- A function, not a string: the number on the shelves is counted out of the
    -- client the visitor is standing in, and nothing counts it until somebody
    -- is in here to be told.
    desc = function()
        return 'Shelves to the ceiling and no window, on the grounds that a window would '
            .. 'cost shelf. One box to a name, the name on the lid in a small careful '
            .. 'hand, and ' .. thousands(cat.shelves()) .. ' of them if the imp up the '
            .. 'ladder has counted right — which it says it has, twice. By the door a '
            .. 'lectern holds the book they were all written from.'
    end,
    exits = { north = 'commons' },
    -- The greeting lands two seconds after the room, for the same reason the
    -- sage's does: said in the same breath as the description it reads as
    -- furniture, and said late it reads as somebody noticing you came in.
    enter = function()
        D.enterTimer = tempTimer(2, function() D.greetImp() end)
    end,
    things = {
        {
            name = 'the imp',
            keys = { 'imp', 'ladder', 'keeper' },
            npc = true,
            presence = function()
                say(C.text, 'An ', cmd('imp', 'look imp', 'look at the imp', C.say),
                    C.text, ' is up the ladder with its back to you, counting boxes it ',
                    'has plainly counted before.')
            end,
            look = function()
                say(C.text, 'Small, ink-black, and entirely uninterested in being looked ',
                    'at. It knows every name in this room and will not give you a box for ',
                    'anything less than the name on its lid — not a nickname, not nearly, ',
                    'not the same word with the capitals rubbed off.')
                say(C.dim, 'Ask it for one: ', cmd('fetch tempAlias', 'fetch tempAlias',
                        'take a box off the shelf', C.dim),
                    C.dim, '. Or take it on: ', cmd('ask about the wager',
                        'ask about the wager', 'three names, said properly', C.dim),
                    C.dim, '.')
            end,
        },
        {
            name = 'the shelves',
            keys = { 'shelves', 'shelf', 'boxes', 'box', 'names' },
            look = function() D.lookShelves() end,
        },
        {
            name = 'the lectern',
            keys = { 'lectern', 'catalogue', 'catalog', 'book', 'list', 'index' },
            url = URL.functions,
            look = function() D.lookCatalogue() end,
        },
        {
            name = 'the door back',
            keys = { 'door', 'wiki', 'wiki door', 'manual' },
            url = URL.wiki,
            look = function()
                say(C.text, 'The way you came in, and on this side of it somebody has ',
                    'written the whole manual out longhand. The commons is through it.')
                say(C.dim, 'The room is the index; the index is a page. ',
                    link('wiki.mudlet.org', URL.wiki), C.dim, ' has the prose that goes ',
                    'round these names — every one of them with an example that runs.')
            end,
        },
    },
}
