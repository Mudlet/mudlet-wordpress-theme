-- Embed settings — deliberately not part of world.lua, so rewriting the world
-- can't quietly lose them. Installed as its own script in the same package.

-- Every visitor gets a throwaway session. Recording it to the profile's log
-- store means IndexedDB writes for the life of the page, for logs nobody can
-- read: the button that browses them is on the toolbar this embed removes.
startLogging(false)
