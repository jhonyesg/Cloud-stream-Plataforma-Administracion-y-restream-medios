# public-video-walkthrough Specification

## Purpose
Lets public pages explain a product flow with a chaptered, sanitized screen recording of the real product, with step navigation, accessible text fallback, a reproducible asset pipeline and a bounded media weight.

## Requirements

### Requirement: Walkthrough renders a chaptered video with step navigation
The system SHALL render a walkthrough as a single `<video>` element plus one step item per chapter, where every step carries the start time of its chapter inside the video. Activating a step SHALL set the video's current time to that chapter's start and start playback.

#### Scenario: Walkthrough renders one video and one item per chapter
- **WHEN** a public page renders a walkthrough with four chapters
- **THEN** the page contains exactly one `<video>` element and four step items, and each step item exposes the start time of its chapter

#### Scenario: Activating a step seeks the video
- **WHEN** a visitor activates the step of the third chapter
- **THEN** the video element's current time becomes the start time of the third chapter and playback starts

### Requirement: Walkthrough shows a screenshot for every step
The system SHALL render, for each step of a walkthrough, a screenshot of the interface that step shows, published as a first-party image next to the step's instructions.

#### Scenario: Each step carries its own screenshot
- **WHEN** a public page renders a walkthrough with four chapters
- **THEN** each step exposes an image path of its own and the four image paths are distinct

#### Scenario: The step screenshot exists on disk
- **WHEN** the image referenced by a step is resolved on the server
- **THEN** the file exists and is readable

### Requirement: Walkthrough media is served from the site's own origin
The system SHALL serve walkthrough media (video and posters) from the same origin as the page, without third-party embeds or third-party media hosts, whether the web server delivers it as a static file or through an application route on the same site.

#### Scenario: Media URLs are first-party
- **WHEN** a visitor inspects the walkthrough markup
- **THEN** the `src` of the `<video>` element, its `poster` attribute and the image of each step point to a path on the site's own origin

### Requirement: Walkthrough media supports byte-range delivery
The system SHALL deliver walkthrough video files with byte-range support, so a browser can seek inside a clip without downloading it completely.

#### Scenario: A range request returns partial content
- **WHEN** a client requests a byte range of a published walkthrough clip
- **THEN** the response status is `206 Partial Content` with a `Content-Range` header covering the requested bytes

### Requirement: Walkthrough media is silent by default and lightweight
The system SHALL NOT start walkthrough media with audible sound, SHALL declare `muted`, `playsinline` and `preload="none"` on the media element, and the total weight of the media referenced by one page SHALL NOT exceed 6 MB.

#### Scenario: Media never starts with sound
- **WHEN** the walkthrough section is rendered
- **THEN** the `<video>` element declares `muted`, `playsinline` and `preload="none"` and no playback is started automatically with sound

#### Scenario: Page media budget is respected
- **WHEN** the media assets referenced by a public page are summed
- **THEN** their combined size is at most 6 MB

### Requirement: Walkthrough media is sanitized before publication
Media published for a walkthrough SHALL NOT contain third-party personal data (e-mail addresses, personal account or channel names, unrelated browser tabs, domains or credentials) nor third-party broadcast content, and SHALL be released only after a written sign-off against the sanitization checklist.

#### Scenario: Sanitization checklist gates publication
- **WHEN** walkthrough media is staged for publication
- **THEN** a sign-off record exists stating that no personal data and no third-party broadcast content is present

### Requirement: Walkthrough content is available as text
The system SHALL render the full text of every step in the HTML independently of the video, and SHALL provide an accessible name for the video element.

#### Scenario: Steps are readable without playing the video
- **WHEN** a visitor loads a page with the media blocked or unavailable
- **THEN** the heading and the text of every step are present in the response body and the section keeps its layout

#### Scenario: Video has an accessible name
- **WHEN** the walkthrough is rendered
- **THEN** the `<video>` element carries an `aria-label` describing the recorded flow

### Requirement: Walkthrough respects the reduced-motion preference
The system SHALL NOT start walkthrough media automatically for visitors whose system reports a preference for reduced motion.

#### Scenario: Reduced motion disables automatic start
- **WHEN** the visitor's system prefers reduced motion
- **THEN** the walkthrough media is not started automatically

### Requirement: Walkthrough assets are reproducible
The system SHALL provide a script that, given a source recording and a list of chapters, produces one clip and one poster per chapter with documented and repeatable parameters.

#### Scenario: Running the build script regenerates the assets
- **WHEN** the build script is executed with the chapter list
- **THEN** one clip and one poster per chapter are (re)generated from the source recording

### Requirement: Walkthrough block is reusable across public pages
The system SHALL render the walkthrough from supplied data (media paths, chapters, titles and texts) without page-specific markup, so the same block can serve more than one public page.

#### Scenario: The same block renders different content on two pages
- **WHEN** two public pages render the walkthrough with different chapters
- **THEN** each page shows its own chapters and media without duplicating the block's markup in the page templates
