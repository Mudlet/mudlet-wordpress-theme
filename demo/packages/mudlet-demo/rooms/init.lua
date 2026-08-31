-- The world ------------------------------------------------------------------
--
-- Each thing carries the noun the parser matches (keys[1] is canonical, the
-- rest are the phrasings a visitor might reach for), the name it is listed
-- under, an optional url that `take` opens, and what looking at it prints.
--
-- One file per room. Adding a room is a file here and a line below — and, for
-- now, a square and a pair of exits in map.lua as well, which is the next thing
-- to go: the exits are already declared here, in the room that has them.

local D = demo

D.rooms = {
    home     = require('mudlet-demo.rooms.home'),
    news     = require('mudlet-demo.rooms.news'),
    vault    = require('mudlet-demo.rooms.vault'),
    commons  = require('mudlet-demo.rooms.commons'),
    workshop = require('mudlet-demo.rooms.workshop'),
    makers   = require('mudlet-demo.rooms.makers'),
}
