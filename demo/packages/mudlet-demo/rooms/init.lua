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
}

-- Returned as well as assigned, so map.lua can require the rooms rather than
-- rely on having been loaded after them.
return D.rooms
