-- The one thing here that is not a parody of a page ---------------------------
--
-- The front page claims Mudlet is scriptable in Lua, and the hero demonstrates
-- everything except that: a map, a real mapper, links, a clerk reading GitHub
-- off the wire. So the Workshop gives the visitor a trigger to write, and pays
-- them in the word it fires on.
--
-- Half the claim, and this is the half where the client reacts to what the
-- world says. The other half — the client reshaping what the visitor says — is
-- an alias, and it is in mudlet-demo/catalogue.lua, two rooms away, for the
-- reason that a trigger and an alias point in opposite directions and one room
-- teaching both would teach neither.
--
-- The demonstration is the order it happens in. The clerk names the fee first,
-- in an ordinary line -- the word goes past in the same colour as everything
-- around it, which is what a game looks like before anybody has scripted it.
-- Then the visitor writes the trigger. Then the clerk pays, and the same word
-- comes back lit. Nothing is claimed that has not been shown twice.
--
-- None of it is mimed, and one call is the reason. A trigger fires on what
-- arrives from the game, and this world has no game: say() is decho, the client
-- talking to itself, which the trigger engine never sees. The payment goes out
-- through dfeedTriggers instead -- the same decimal-RGB text, handed to the
-- engine as though a server had sent it. That call is the whole difference
-- between a trigger firing and a world colouring a word in and calling it one.
--
-- The trigger is a tempTrigger with a Lua callback, and the highlight is
-- selectString + fg + resetFormat: the idiom out of any Mudlet package, run by
-- the real thing.
--
-- What the clerk offers is that call, in full, to click or to type. A `trigger
-- on gold` verb of our own would have been shorter to read and would have
-- proved nothing: the visitor would have typed a word this world made up, and
-- taken our word for what happened next. The Lua is the client's, not ours --
-- it runs through run-lua-code, which is in this profile for exactly this, and
-- it is the same line that would work in a desktop Mudlet against a real game.
-- The verb still exists for anybody who would rather not read Lua at all.

local D = demo
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local C, say, cmd, link = core.C, core.say, core.cmd, core.link

-- What the clerk pays with, and so what the trigger is for. One constant,
-- because the word appears in the fee, in the suggested command and in the
-- payment, and a demonstration whose three halves disagree teaches the wrong
-- lesson.
local FEE = 'gold'

-- The call the clerk puts up, and the one D.watchFor() runs for anybody who
-- takes the shortcut instead. Written once, so what is shown and what is run
-- cannot drift.
-- The guard is not decoration. selectString returns the column it found the
-- text at, or -1 -- and on -1 it leaves the *previous* selection in place, so
-- an unchecked fg() recolours whatever was selected last instead of doing
-- nothing. Here the trigger and the search test the same string and it would
-- rarely miss; in the regex trigger somebody writes after copying this line,
-- the matched text and the searched text are not the same string at all.
local function luaFor(w)
    return string.format(
        'lua tempTrigger("%s", function() if selectString("%s", 1) > -1 then '
            .. 'fg("gold") resetFormat() end end)',
        w, w)
end
local LUA = luaFor(FEE)

-- The visitor's trigger. Kept so a second one replaces the first rather than
-- stacking, and so `trigger off` has something to kill. `offered` is what makes
-- the second ask a payment rather than the same speech again.
local id, watching, offered, paid

-- The clerk names the fee.
--
-- Deliberately an ordinary line: `gold` goes past in the body colour, unmarked,
-- and the visitor has no reason to notice it. That is the before.
function D.commission()
    -- Asked once, the clerk names the job. Asked again, the clerk pays -- which
    -- is what makes the Lua above worth running before asking twice, and what
    -- lets somebody try a different word and ask again to see it not match.
    if offered then D.pay() return end

    say()
    say(C.text, 'The clerk licks a thumb, turns the book round, and taps a line half ',
        'way down it.')
    say(C.say, '"One job going. Tell me the moment a word comes past in the book. ',
        'There is a ', FEE, ' coin in it for you."')
    say()
    say(C.dim, 'You could watch for it yourself. Or you could have the client watch ',
        'for you, which is the entire reason it exists. This is the whole of it:')
    say()
    -- The label and the command are the same string on purpose: what is printed
    -- is what runs, so reading it and clicking it teach the same thing. It is
    -- long, and it wraps, and that is the point - this is the real call.
    say(C.dim, '  ', cmd(LUA, LUA, 'run it in this client, for real', C.exit))
    say()
    -- Said here rather than left to be inferred. A visitor who has only ever
    -- seen that line could reasonably conclude that triggers are a Lua
    -- feature typed at a command line, which is backwards: the editor is how
    -- most people make most triggers, and it is missing here only because
    -- this embed hides the toolbar (see demo/src/embed.css).
    say(C.dim, 'That is not the usual way, mind. In a full Mudlet you would open ',
        'the ', link('Triggers editor', URL.triggers), C.dim,
        ' and fill in a pattern and a script — no brackets, no quotes. The line ',
        'above is the same trigger made from the command line, which is what ',
        'packages do, and what this world has to do: the toolbar is hidden in ',
        'here.')
    say()
    say(C.dim, 'Click it, or type it, or type something else in place of ',
        C.exit, FEE, C.dim, ' and watch it not match. Then ',
        cmd('ask again', 'ask about work', 'the clerk pays up', C.dim),
        C.dim, '.')
    offered = true
end

-- Make it.
function D.watchFor(w)
    if w == '' then
        say(C.text, 'A trigger needs a word to watch for. The clerk is paying for ',
            C.exit, FEE, C.text, ':')
        say(C.dim, '  ', cmd('trigger on ' .. FEE, 'trigger on ' .. FEE,
            'make a real Mudlet trigger for that word', C.dim))
        return
    end

    D.triggerOff(true)
    watching = w
    id = tempTrigger(w, function()
        -- Same guard, same reason as luaFor() above.
        if selectString(w, 1) > -1 then
            fg('gold')
            resetFormat()
        end
    end)

    say()
    say(C.text, 'Mudlet takes it. Every line from here on is checked for ',
        C.exit, w, C.text, '.')
    -- The Lua that shortcut just stood in for, so even the lazy path shows its
    -- working.
    say(C.dim, '  ', luaFor(w))
    say(C.dim, '  -> trigger ', tostring(id), '   ·   ',
        cmd('trigger off', 'trigger off', 'kill it again', C.dim))

    -- A beat, so the payment reads as the clerk answering rather than as more
    -- of the same paragraph.
    tempTimer(1.6, function() D.pay() end)
end

-- The payment, and the point of the file.
--
-- dfeedTriggers and not say(): see the header. What lights the word up is the
-- visitor's trigger, not a colour written in here -- which is checkable, and
-- worth checking, by watching for any other word instead and seeing this line
-- arrive plain.
function D.pay()
    -- The coin is paid once. Asking twice used to mint another, which is a worse
    -- bug than it looks: what this room demonstrates is that the world is
    -- consistent and the client is real, and a clerk who pays forever is neither.
    --
    -- The line still goes out either way and still carries the word, because the
    -- instruction above it says to try another word and ask again. The
    -- demonstration is repeatable; the coin is not.
    if paid then
        dfeedTriggers(C.text .. 'The clerk taps a line in the book without looking '
            .. 'up. "Paid you already. One ' .. FEE .. ' coin, and the ink is dry."\n')
        return
    end

    paid = true
    dfeedTriggers(C.text .. 'The clerk turns over a ' .. FEE ..
        ' coin, and puts it in your palm.\n')

    -- Three things the world might know, and it says only what it does know.
    -- A trigger made by running the Lua directly is the client's business and
    -- not this package's: nothing here can ask what patterns exist, which is
    -- exactly right - the visitor scripted the client, not the world.
    if watching == FEE then
        say(C.dim, 'Your trigger caught it on the way past. Nothing in this room ',
            'coloured that word in — the line arrived, the pattern matched, Mudlet ',
            'did the rest.')
    elseif watching then
        say(C.dim, 'Your trigger is watching for ', C.exit, tostring(watching),
            C.dim, ', so it let that line go by untouched. Which is the proof: ',
            'it either matches or it does not.')
    else
        say(C.dim, 'That line went through the trigger engine on its way to the ',
            'screen, the same as every line a game sends. If you made a trigger for ',
            'a word in it, you just watched it fire.')
    end
end

-- Kill it. Silent when the world is replacing one trigger with another; a line
-- when the visitor asked.
function D.triggerOff(quiet)
    if id then killTrigger(id) end
    local had = watching
    id, watching = nil, nil

    if quiet then return end
    if had then
        say(C.text, 'The trigger is gone. Lines are just lines again.')
    else
        say(C.text, 'There is no trigger to remove.')
    end
end

return { FEE = FEE }
