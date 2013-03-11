window.onload = function() {
	new Timeframe('calendars', {
      startField: 'start',
      endField: 'end',
	  weekOffset: 1,
      earliest: new Date(),
	  format: '%Y-%m-%d',
	  months: 1});
};
