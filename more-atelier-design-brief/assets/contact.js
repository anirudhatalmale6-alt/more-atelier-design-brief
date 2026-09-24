/**
 * Contact Form 7 forms, behaving like the brief.
 *
 * Only two behaviours are needed: the message field grows with the answer
 * instead of sitting in a fixed rectangle, and option lists that were laid out
 * two-up keep that arrangement.
 */
(function () {
  'use strict';

  var forms = [].slice.call(document.querySelectorAll('.wpcf7-form'));
  if (!forms.length) { return; }

  // Inline `!important`: the old site CSS pins this textarea's height with
  // !important, so a plain inline height loses and the field stays a tall box.
  // Setting it inline-important lets the measurement actually take effect —
  // and reset-then-measure, or it can only ever grow.
  function grow(ta) {
    ta.style.setProperty('height', 'auto', 'important');
    ta.style.setProperty('height', ta.scrollHeight + 'px', 'important');
  }

  forms.forEach(function (form) {
    [].forEach.call(form.querySelectorAll('textarea'), function (ta) {
      // CF7 renders these with rows="10", and `height:auto` on a textarea means
      // "as tall as its rows" — which is why it still opened as a deep box even
      // once the border was gone. One row, then let it grow with the answer.
      ta.rows = 1;
      ta.addEventListener('input', function () { grow(ta); });
      grow(ta);
    });

    // Four or more options read better in two columns, which is how his
    // Service list was already arranged before the restyle.
    [].forEach.call(form.querySelectorAll('.wpcf7-checkbox, .wpcf7-radio'), function (group) {
      if (group.querySelectorAll('.wpcf7-list-item').length >= 4) {
        group.classList.add('ma-two-up');
      }
    });
  });

  // CF7 empties the form after a successful send; the textarea must shrink back.
  document.addEventListener('wpcf7mailsent', function (e) {
    var form = e.target && e.target.querySelector ? e.target : document;
    [].forEach.call(form.querySelectorAll('.wpcf7-form textarea, textarea'), grow);
  });
})();
