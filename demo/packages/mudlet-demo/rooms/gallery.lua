-- The Gallery, east of the front page: /media/.
--
-- The one page on the site whose whole content is content — no template, no
-- post type, nothing derived — and so the last page the world had no room for.
-- It is here because of what it costs the visitor to see it: every other room
-- prints, and this one fetches. See mudlet-demo/frame.lua, which does the
-- hanging and is where the lesson lives.

local SITE = require('mudlet-demo.site')
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd
local spell, spellCap = core.spell, core.spellCap

return {
    title = 'The Gallery',
    desc = function()
        local shots = SITE.media.count
        return 'A long room with a skylight and one clear wall, hung floor to ceiling '
            .. 'with other people\'s screens: '
            .. (shots > 0 and (spell(shots) .. ' framed screenshots, none of them '
                .. 'taken here') or 'frames, all of them turned to face the wall')
            .. '. In the corner a projector points at nothing in particular, and there '
            .. 'is a bare hook in the middle of the room at about eye height.'
    end,
    exits = { west = 'home' },
    things = {
        {
            name = 'the wall of frames',
            keys = { 'wall', 'frames', 'frame', 'screenshots', 'screenshot', 'pictures',
                'picture', 'photos' },
            url = URL.media,
            look = function()
                local wall = SITE.media.shots
                if #wall == 0 then
                    say(C.text, 'Turned around, every one of them, and the backs are just ',
                        'brown paper and staples. There is no site behind this frame to ',
                        'ask for a picture — a dev server, or a copy on a disk — and a ',
                        'screenshot is not a thing a world can invent for itself.')
                    say(C.dim, 'They hang for real at ', link('mudlet.org/media', SITE.media.url),
                        C.dim, ', which is the page this room is standing in for.')
                    return
                end

                say(C.text, spellCap(#wall), ' of them are within reach, and every one is ',
                    'somebody else\'s session — their fonts, their colours, their idea of ',
                    'where a map goes. That is the argument the room is making.')
                say()
                for i, shot in ipairs(wall) do
                    say(C.dim, ('%2d. '):format(i),
                        cmd(shot.title ~= '' and shot.title or ('screenshot ' .. i),
                            'hang ' .. i, 'take this one down and hang it on the hook'))
                end
                say()
                say(C.dim, 'Take one down and put it on the hook — a real download into ',
                    'this profile, and a real ', link('Geyser', URL.geyser), C.dim,
                    ' label drawn over the console to hang it in.')
            end,
        },
        {
            name = 'the hook',
            keys = { 'hook', 'nail', 'eye height' },
            grab = function() demo.hang() end,
            look = function()
                say(C.text, 'One hook, at eye height, with nothing on it. Everything else in ',
                    'this world is text — this is the one place the client is asked to put ',
                    'something on the screen that is not.')
                say(C.dim, 'Mudlet draws its interface with ', link('Geyser', URL.geyser),
                    C.dim, ', which is how the bar above this console got there, and how ',
                    'every screenshot on that wall got the shape it has. ',
                    cmd('Hang one', 'hang', 'hang a picture on the hook', C.dim), C.dim,
                    ' and watch it built.')
            end,
        },
        {
            name = 'the projector',
            keys = { 'projector', 'reel', 'films', 'film', 'screencasts', 'screencast',
                'videos', 'video' },
            url = URL.media,
            look = function()
                local reel = SITE.media.films
                if #reel == 0 then
                    say(C.text, 'The reel is off the spool and there is nothing threaded ',
                        'through the gate. Whoever recorded these keeps them on the site, ',
                        'and the site is not answering.')
                    say(C.dim, 'They are at ', link('mudlet.org/media', SITE.media.url),
                        C.dim .. '.')
                    return
                end
                say(C.text, spellCap(#reel), ' reels, wound and labelled. Somebody sat down ',
                    'and recorded these so that the next person would not have to work it ',
                    'out from the manual.')
                say()
                for _, film in ipairs(reel) do
                    say(C.dim, '  · ', link(film.title, film.url))
                end
                say()
                say(C.dim, 'They play in a browser rather than in here. A MUD client that ',
                    'showed you video would be a browser.')
            end,
        },
    },
}
