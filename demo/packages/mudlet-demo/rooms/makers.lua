-- Makers Hall, west of the commons. The sage at the head of the table keeps the
-- ledger; the ledger itself, and everything the sage says out of it, is in
-- mudlet-demo/people.lua.

local D = demo
local SITE = require('mudlet-demo.site')
local core = require('mudlet-demo.core')
local URL = require('mudlet-demo.urls')
local C, say, link, cmd = core.C, core.say, core.link, core.cmd
local spellCap = core.spellCap

return {
    title = 'Makers Hall',
    desc = 'A hall with one very long table down the middle and more chairs than there '
        .. 'are people — some of the names cut into the chairbacks have not been sat in '
        .. 'for a decade, and nobody has moved them out. At the head of the table a sage '
        .. 'keeps the ledger of everyone who ever built anything here.',
    exits = { east = 'commons' },
    -- The sage greets you a beat after you arrive rather than in the same
    -- breath as the room description: a greeting printed with the room
    -- reads as part of the furniture, and one that lands two seconds later
    -- reads as somebody noticing you came in.
    enter = function()
        D.enterTimer = tempTimer(2, function() D.greet() end)
    end,
    things = {
        {
            name = 'the sage',
            keys = { 'sage', 'keeper' },
            -- The only living thing in the world. It is listed on its
            -- own line rather than among the furniture, and the *name*
            -- carries the colour it speaks in while the sentence around it
            -- stays narration — colouring the whole line gold said "this
            -- line is different" without saying which word was the sage.
            npc = true,
            presence = function()
                say(C.text, 'A ', cmd('sage', 'look sage', 'look at the sage', C.say),
                    C.text, ' sits at the head of the table, one hand flat on the ledger.')
            end,
            look = function()
                say(C.text, 'Patient, and slightly ink-stained. The sage has read every commit ',
                    'message since 2008 and remembers who wrote what, which is a stranger ',
                    'kind of memory than it sounds.')
                say(C.dim, 'Ask about anyone: ',
                    cmd('ask about vadi', 'ask about vadi', 'ask the sage about Vadim Peretokin', C.dim),
                    C.dim, ', or ', cmd('ask about everyone', 'ask about everyone',
                        'the whole ledger', C.dim), C.dim .. '.')
            end,
        },
        {
            name = 'the ledger',
            keys = { 'ledger', 'book', 'roll' },
            url = URL.makers,
            look = function() D.ledger() end,
        },
        {
            name = 'the chairs',
            keys = { 'chairs', 'chair', 'table', 'names', 'chairbacks' },
            look = function()
                say(C.text, spellCap(SITE.makers.count), ' of them, near enough. Some are ',
                    'pulled right in and warm; ',
                    'some have been pushed back since 2010 and hold a name and one very ',
                    'specific contribution, like a build script or an installer, which is ',
                    'how open source remembers people.')
                say(C.dim, 'The sage will tell you about any of them by name.')
            end,
        },
    },
}
