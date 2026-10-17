# fix-restream-xdata-json-escape

Fix console errors and visual breakage on the Restream module caused by raw @json() output inside a double-quoted HTML attribute, which closes the x-data attribute prematurely and truncates the Alpine data payload.
