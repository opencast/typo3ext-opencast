# TYPO3 Extension opencast

This extension adds support for Opencast videos to TYPO3 10 & 11 LTS.

## Installation

* Install extension via composer: `composer require uos/opencast`
* Go into BE module 'settings' and set `host` and `version` parameter
* Include static TypoScript `Opencast` into your main template (mandatory!)
* Include static TypoScript `Opencast: IFrame CSS` (optional!)

## Usage

* Create new content element that's capable of displaying videos, e.g. `textmedia`
* Hit button 'Add media file', paste Opencast video URL into 'Add new media asset' and 'Add media'
* In case it's been a valid Opencast video URL there should be a new `.opencast` file that you can click on

## Rendering

Currently Opencast videos are being rendered as iframes.

Paths to template file can be overridden with the usual mechanism of setting this TS constants:

```
plugin.tx_opencast.view.templateRootPath = EXT:your_extension/Resources/Private/Tempates/Extensions/Opencast
```

## License

Copyright (C) 2021, Apereo Foundation

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
