# Changelog

## 0.1.0

### New
- Google Analytics 4 and Meta Pixel from an id on the settings page; with no id nothing is added to any page.
- Shop events from the theme (`aisleTrack`) sent to GA4 and, where Meta has a match, to the pixel.
- Built-in consent bar, on by default: no tag loads before Accept, Decline is remembered for 180 days.
- Ids checked on save; admin, API and preview pages never get a tag.
