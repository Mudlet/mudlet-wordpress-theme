-- Where the world points out ---------------------------------------------------
--
-- Every link that leaves this world, in one place. The world is a parody of
-- mudlet.org and each thing in it opens the real page it is parodying, so these
-- are load-bearing rather than decorative.

local URL = {
    home     = 'https://www.mudlet.org/',
    -- Not a page this world is parodying: it is the thing the world is running
    -- in. The visitor is already inside Mudlet Web, and the first line says so.
    web      = 'https://mudlet.github.io/mudlet-web/',
    download = 'https://www.mudlet.org/download/',
    news     = 'https://www.mudlet.org/news/',
    makers   = 'https://www.mudlet.org/the-makers/',
    packages = 'https://packages.mudlet.org/',
    -- The one page on the site that is nothing but its own content: the
    -- screenshots people sent in and the screencasts somebody recorded. The
    -- Gallery east of the front page is that page, and the only room that
    -- fetches something rather than printing it.
    media    = 'https://www.mudlet.org/media/',
    forum    = 'https://forums.mudlet.org/',
    wiki     = 'https://wiki.mudlet.org/',
    -- The manual's own account of what a trigger is, of what an alias is, and of
    -- what a timer is: the visitor is made to write one of each. The first two
    -- have a room apiece; the third is a kettle on a bench, because a timer is
    -- the one of the three that needs the visitor to be somewhere else.
    triggers = 'https://wiki.mudlet.org/w/Manual:Introduction#Triggers',
    aliases  = 'https://wiki.mudlet.org/w/Manual:Introduction#Aliases',
    timers   = 'https://wiki.mudlet.org/w/Manual:Introduction#Timers',
    -- The index of everything Mudlet documents, one anchor per name, which is
    -- what the imp in the Stacks writes on the lid of a box. The catalogue it
    -- reads out of is the machine-readable half of this very page.
    functions = 'https://wiki.mudlet.org/w/Manual:Lua_Functions',
    -- The layout manager the Gallery hands over, and the one this package has
    -- been using for itself since the bar over the console was drawn.
    geyser   = 'https://wiki.mudlet.org/w/Manual:Geyser',
    discord  = 'https://discord.gg/kuYvMQ9',
    github   = 'https://github.com/Mudlet/Mudlet',
    -- The pages the clerk in the Workshop is reading off a wire. Every line
    -- they say carries one of these, so a visitor who gets the apology instead
    -- of the answer is still one click from the thing itself.
    commits  = 'https://github.com/Mudlet/Mudlet/commits',
    pulls    = 'https://github.com/Mudlet/Mudlet/pulls',
    issues   = 'https://github.com/Mudlet/Mudlet/issues',
    firstish = 'https://github.com/Mudlet/Mudlet/issues?q=is%3Aissue+is%3Aopen+label%3A%22good+first+issue%22',
    ptb      = 'https://make.mudlet.org/snapshots/?platform=all&source=ptb',
    -- The download manager's own links, which is what the real buttons point
    -- at: each one redirects straight to the installer, so opening it puts the
    -- file in the visitor's downloads rather than showing them another page.
    -- The ids belong to whichever release is current and move with each one —
    -- unlike the version, weights and hashes typed into site.lua, which
    -- are the July 2026 snapshot.
    win      = 'https://www.mudlet.org/download/70/',
    macx     = 'https://www.mudlet.org/download/69/',
    macarm   = 'https://www.mudlet.org/download/68/',
    linux    = 'https://www.mudlet.org/download/67/',
    post422  = 'https://www.mudlet.org/2026/07/4-22-mapping-made-friendlier/',
    post4220 = 'https://www.mudlet.org/2026/07/mudlet-4-22-0/',
    post421  = 'https://www.mudlet.org/2026/06/4-21-mudlet-made-better/',
}

return URL
