-- mudlet.org, walked instead of scrolled.
--
-- Six rooms stand in for the site: the front page, the release downloads, the
-- news archive, the places the site links out to, the people who built it and
-- the workshop they build it in. Everything a visitor can open here opens the
-- real page it is parodying — the descriptions carry the links, so
-- `look windows` is the download page's Windows row and the link in it is that
-- row's button.
--
-- One room is the exception to all of that, and it is the last one: the
-- Workshop does not know what it says until somebody asks, because what landed
-- this week is not a fact about the site at all. See mudlet-demo/github.lua.
--
-- Nothing has to be typed. Every noun, exit and suggested command prints as a
-- clickable link, so the whole world is playable with a mouse — which is the
-- point in a homepage hero, where most visitors will click before they type.
--
-- The prose is written; the facts inside it are not. Versions, weights, hashes,
-- headlines and counts are asked of the site this is framed in, once, over one
-- REST call while the console animates its connect — see mudlet-demo/site.lua.
-- What is written into SITE is the July 2026 snapshot, and it is what the world
-- says anywhere there is no site to ask: the prototype page, a dev server, a
-- file:// copy.
--
-- The package is a directory of modules, loaded from the profile's own copy of
-- it: an .mpackage is unzipped into <profile>/<packageName>/, and Mudlet Web
-- seeds package.path with that directory, so every file below is require()d by
-- path rather than pasted into one script node. A file's own header says what
-- it is for.

demo = demo or {}
local D = demo

-- Order is documentation rather than necessity — each file requires what it
-- needs — but it is the order the world reads in: the rooms, the two people who
-- answer questions in them, the map over the top, the verbs that move you, the
-- request that tells the prose what year it is, and the connect that starts it.
require('mudlet-demo.rooms')
require('mudlet-demo.people')
require('mudlet-demo.github')
require('mudlet-demo.map')
require('mudlet-demo.verbs')
require('mudlet-demo.seed')
require('mudlet-demo.boot')

tempTimer(0.4, function() D.boot() end)
