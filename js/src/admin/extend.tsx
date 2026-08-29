import Extend from 'flarum/common/extenders';
import extractText from 'flarum/common/utils/extractText';

import PlacementsPage from './components/PlacementsPage';
import { PERMISSIONS, trans } from './config';

export default [
  new Extend.Admin()
    // A custom page rather than a settings list: campaigns are records, and a
    // settings list has nowhere to put them.
    .page(PlacementsPage)

    .permission(
      () => ({
        icon: 'fas fa-eye-slash',
        label: trans('permissions.view_without_ads'),
        permission: PERMISSIONS.viewWithoutAds,
        allowGuest: false,
      }),
      'view',
      100
    )

    .permission(
      () => ({
        icon: 'fas fa-rectangle-ad',
        label: trans('permissions.manage'),
        permission: PERMISSIONS.manage,
      }),
      'moderate',
      90
    )

    .permission(
      () => ({
        icon: 'fas fa-paper-plane',
        label: trans('permissions.submit'),
        permission: PERMISSIONS.submit,
      }),
      'start',
      80
    )

    // Its own row, well away from "manage": an HTML creative runs as
    // same-origin JavaScript on every page of the forum, including the one an
    // administrator is looking at.
    .permission(
      () => ({
        icon: 'fas fa-code',
        label: trans('permissions.author_html'),
        permission: PERMISSIONS.authorHtml,
      }),
      'moderate',
      70
    )

    // So that an administrator searching the admin panel for "ads" lands here
    // without having to know what the extension is called.
    .generalIndexItems('permissions', () => [
      {
        id: PERMISSIONS.viewWithoutAds,
        label: extractText(trans('permissions.view_without_ads')),
      },
      {
        id: PERMISSIONS.manage,
        label: extractText(trans('permissions.manage')),
      },
    ]),
];
