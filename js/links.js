/*
 *	This Program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3, or (at your option)
 *  any later version.
 *
 *  This Program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with TsunAMP; see the file COPYING.  If not, see
 *  <http://www.gnu.org/licenses/>.
 * 
 *	TCMODS Edition 
 *
 *	links.js
 *	- author: unknown
 *	- modifies links so they stay within homescreen app on IOS
 *	- certain links are excluded
 *
 *	TC (Tim Curtis) 2014-12-23, r1.3
 *	- added this program header
 *	- added class names to the "dont modify list"
 *	- added this.className == "active" && $(this).attr("tabindex") == 0 to cover input dropdowns on config pages (the items are <a links) 
 *
 *	TC (Tim Curtis) 2015-05-30, r1.9
 *	- added this.className == "playhistory-link" to exclusion list
 *
 */
$(document).on("click", "a", function(event) {
		//console.log('links.js: this.id=', this.id);
		//console.log('links.js: this.className=', this.className);
		//console.log('links.js: this.attributes=', this.attributes);
		//console.log('links.js: $(this).attr(tabindex)', $(this).attr("tabindex"));
		//return;

	    // don't modify link if matches condition below
		if (this.id == "menu-settings" || 
			this.id == "coverart-link" || 
			this.className == "tcmods-about-link1" || 
			this.className == "tcmods-about-link2" ||
			this.className == "playhistory-link" || 
			// input dropdowns on config pages
			(this.className == "active" && $(this).attr("tabindex") == 0)) {
				
			//console.log('links.js: link not modified, match found in exclusion list');
			return;
		} 
		
	    if (!$(this).hasClass("external")) {
			//console.log('links.js: link will be modified, does not have class external');
	        event.preventDefault();
	        if (!$(event.target).attr("href")) {
       			//console.log('links.js: link modified, case 1: does not have attr href');
	            location.href = $(event.target).parent().attr("href");
	        } else {
       			//console.log('links.js: link modified, case 2: has attr href');
	            location.href = $(event.target).attr("href");
	        }
	    } else {
			//console.log('links.js: link not modified, not in exclusion list but has class external');
			// place holder   
	    }
    }
);
