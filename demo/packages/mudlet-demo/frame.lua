-- Hanging a picture -----------------------------------------------------------
--
-- The three things this world hands over — a trigger in the Workshop, an alias
-- in the Stacks, a timer on the bench — are all the client doing something to
-- text. This is the other half of Mudlet, and the half the site's screenshots
-- are actually of: the client putting something on the screen that is not text
-- at all. The demo builds Geyser for itself in map.lua and never lets the
-- visitor near it. The Gallery lets them near it.
--
-- The whole of it:
--
--     downloadFile(getMudletHomeDir() .. '/gallery-3.png', 'https://…/shot.png')
--     -- ... sysDownloadDone ...
--     Geyser.Label:new({ name = 'demoPicture', … })
--     label:setStyleSheet('background-image: url("…")')
--
-- None of which the room prints. Every other thing this world hands over shows
-- its working — the kettle prints its tempTimer, the imp prints its alias —
-- because there the Lua *is* the demonstration and there is nothing else to
-- look at. Here there is: the argument is the picture, and four lines of source
-- under it is the console taking the room back off the visitor. The manual is
-- one click away in the prose for anyone who wants the how.
--
-- Every link in that chain was an open question in a browser build, and each
-- was measured before this file was written:
--
--   * downloadFile works, and writes into the profile's own directory — which
--     here is IndexedDB wearing a filesystem. The file reads back from Lua at
--     the size it arrived.
--   * A label's *stylesheet* is what draws the picture. setBackgroundImage()
--     returns without complaining and is not what put the image on the screen,
--     so it is not what this uses.
--   * A label is the only widget that is transparent over the console — a
--     miniconsole's background alpha does nothing here — and redrawing a
--     label's contents is nearly free, while moving one is ruinous. Nothing in
--     this file animates.
--
-- This is also the only fetch this world makes that is not the seed, and it
-- happens because somebody walked into one room and asked for one picture.
-- Nothing is downloaded for anybody who does not.

local D = demo
local core = require('mudlet-demo.core')
local SITE = require('mudlet-demo.site')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd

local NAME = 'demoPicture'
-- Centred, and hung just under the bar — a picture on a wall is at eye height
-- and in the middle of it, not tucked in a corner. The top thirty pixels are
-- the bar's and the top right corner is the mapper's, both map.lua's, so this
-- starts below the one and is narrow enough to stay clear of the other.
local TOP = 30
local INSET = 14
-- No wider than this and no more than this much of the console. The room is in
-- a homepage hero as often as it is in a full window, and a picture sized for
-- one buries the other.
local MAX_W, SHARE, TALLEST = 480, 0.45, 0.55

local hung, showing, pending = nil, nil, nil
-- What has been fetched already, keyed by url. A profile is a real directory
-- and it survives a reload, so the second hanging of the same picture usually
-- costs nothing — which is worth saying out loud when it happens, because it
-- is the profile proving it exists.
local saved = {}

-- The frame, cut to the picture. This collection runs from 0.86 to 1.86 wide;
-- a fixed box would letterbox half of it and crop the other half.
local function box(shot)
    local ww, wh = getMainWindowSize()
    local ratio = (shot.w > 0 and shot.h > 0) and (shot.h / shot.w) or 0.625
    local w = math.min(MAX_W, math.floor(ww * SHARE))
    local h = math.floor(w * ratio)
    local tallest = math.floor((wh - 30) * TALLEST)
    if h > tallest then
        h = tallest
        w = math.floor(h / ratio)
    end
    return w, h
end

local function draw(shot, path)
    local w, h = box(shot)

    if not hung then
        hung = Geyser.Label:new({ name = NAME, x = 0, y = 0, width = w, height = h })
        -- A label eats every click that lands on it, and everything clickable
        -- in this world is a dechoLink underneath it. Rather than fight that,
        -- the picture is the control: clicking it takes it down again.
        hung:setClickCallback(function() D.unhang() end)
    end
    hung:resize(w, h)
    -- Centred by arithmetic rather than by a percentage: Geyser anchors a box
    -- by its top left corner, so "in the middle" is a number this has to work
    -- out, and it works it out again on every hanging because the window it is
    -- centred in is a hero on one page and a whole tab on another.
    local ww = getMainWindowSize()
    hung:move(math.max(0, math.floor((ww - w) / 2)), TOP + INSET)
    hung:setStyleSheet(string.format([[
        background-image: url("%s");
        background-repeat: no-repeat;
        background-position: center;
        background-size: contain;
        background-color: #1b1611;
        border: 1px solid #6f9cb8;
    ]], path))
    hung:show()
    showing = shot

    say(C.text, 'It hangs over everything, because that is what a Geyser label does — ',
        w, ' by ', h, ', and no part of this console knows it is there. ',
        cmd('Take it down', 'take down the picture', 'or just click the picture', C.text),
        C.text, ' when you have looked at it.')
end

-- One handler pair for the whole room, registered as the package loads. Both
-- filter on the name this file writes under: an anonymous handler hears every
-- download this client ever makes, including the ones it did not ask for.
local function ours(file)
    return type(file) == 'string' and file:find('gallery-', 1, true) ~= nil
end

registerAnonymousEventHandler('sysDownloadDone', function(_, file, size)
    if not ours(file) or not pending then return end
    local shot = pending
    pending = nil
    saved[shot.url] = file
    draw(shot, file)
end)

registerAnonymousEventHandler('sysDownloadError', function(_, err, file)
    if file ~= nil and not ours(file) then return end
    if not pending then return end
    pending = nil
    -- The clerk's rule, one room over: every way this can fail says so in its
    -- own voice and carries the link to the thing it could not reach.
    say(C.text, 'The hook comes away from the wall with the plaster. ', tostring(err))
    say(C.dim, 'The picture is on ', link('the media page', SITE.media.url), C.dim,
        ' either way, at full size, in a browser that is better at this than a MUD ',
        'client is.')
end)

-- `hang 3`, `hang aardwolf`, `hang`, or clicking a frame on the wall.
function D.hang(which)
    local wall = SITE.media.shots

    if #wall == 0 then
        say(C.text, 'The frames are all turned to face the wall, and there is nothing ',
            'behind them. This world is not inside mudlet.org at the moment — a dev ',
            'server, or a copy on somebody\'s disk — so there is no site to ask for a ',
            'picture, and nothing here invents one.')
        say(C.dim, 'The real wall is at ', link('mudlet.org/media', SITE.media.url), C.dim .. '.')
        return
    end

    local n = tonumber((which or ''):match('%d+'))
    if not n then
        -- A word rather than a number: the nearest title, the way `look` finds
        -- a thing by any of the nouns it answers to.
        local wanted = (which or ''):lower()
        for i, shot in ipairs(wall) do
            if wanted ~= '' and shot.title:lower():find(wanted, 1, true) then n = i break end
        end
    end
    n = n or math.random(1, #wall)

    local shot = wall[n]
    if not shot then
        say(C.text, 'There is no frame with that number. There are ', #wall, ' of them.')
        return
    end
    if showing == shot then
        say(C.text, 'That one is already up.')
        return
    end
    if pending then
        say(C.text, 'One is already on its way down. Give it a moment.')
        return
    end

    say()
    say(C.text, 'You lift it down.', shot.title ~= '' and (' ' .. shot.title .. '.') or '')

    local already = saved[shot.url]
    if already then
        say(C.dim, '  it is still in this profile from earlier — a profile is a real ',
            'directory on a real disk, and nothing had to be fetched twice')
        draw(shot, already)
        return
    end

    local ext = shot.url:match('%.(%w%w?%w?%w?)$') or 'png'
    local path = getMudletHomeDir() .. '/gallery-' .. n .. '.' .. ext

    pending = shot
    downloadFile(path, shot.url)
end

function D.unhang(quiet)
    if not hung or not showing then
        if not quiet then say(C.text, 'Nothing is hanging.') end
        return
    end
    hung:hide()
    showing = nil
    if quiet then return end
    say(C.text, 'You take it down and lean it against the wall with the others.')
end

return { hang = D.hang, unhang = D.unhang }
