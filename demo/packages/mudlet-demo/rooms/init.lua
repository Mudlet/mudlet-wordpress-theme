-- The world ------------------------------------------------------------------
--
-- Each thing carries the noun the parser matches (keys[1] is canonical, the
-- rest are the phrasings a visitor might reach for), the name it is listed
-- under, an optional url that `take` opens, and what looking at it prints.
--
-- One file per room, and the room is the only place its exits are written down:
-- map.lua walks them to work out where it is drawn and what id it gets. Adding a
-- connected room is a file here and a line below, and nothing else anywhere.

local D = demo

D.rooms = {
    home     = require('mudlet-demo.rooms.home'),
    news     = require('mudlet-demo.rooms.news'),
    vault    = require('mudlet-demo.rooms.vault'),
    commons  = require('mudlet-demo.rooms.commons'),
    workshop = require('mudlet-demo.rooms.workshop'),
    makers   = require('mudlet-demo.rooms.makers'),
    stacks   = require('mudlet-demo.rooms.stacks'),
    gallery  = require('mudlet-demo.rooms.gallery'),
    cellar   = require('mudlet-demo.rooms.cellar'),
}

-- The ways out of a room, sorted. Both things that show a visitor their exits
-- go through here — the console's "Exits:" line in verbs.lua and the bar over
-- it in map.lua — so the two are the same list because they are the same call,
-- rather than two loops that agree until one of them is edited. pairs() over an
-- exit table has no order, and a row whose words reshuffle between one look and
-- the next is worse than no row at all.
--
-- On D rather than on the table above, because that table is iterated as
-- rooms — a function in it would be a room named after itself.
function D.waysOut(key)
    local out = {}
    for dir in pairs(D.rooms[key].exits) do out[#out + 1] = dir end
    table.sort(out)
    return out
end

-- Returned as well as assigned, so map.lua can require the rooms rather than
-- rely on having been loaded after them.
return D.rooms
