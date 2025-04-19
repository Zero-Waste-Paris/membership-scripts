import { Component } from '@angular/core';
import { TimestampedSlackUserList } from '../generated/api/model/timestampedSlackUserList';
import { DataProviderService } from '../data-provider.service';
import { DefaultService } from '../generated/api/api/default.service';
import { NgIf, NgFor, DatePipe } from '@angular/common';
import { MatProgressSpinnerModule } from '@angular/material/progress-spinner';

@Component({
    selector: 'app-slack-outdated',
    imports: [NgIf, NgFor, DatePipe, MatProgressSpinnerModule],
    templateUrl: './slack-outdated.component.html',
    styleUrl: './slack-outdated.component.css'
})
export class SlackOutdatedComponent {
	data: TimestampedSlackUserList|null = null;

	constructor(
		dataProvider: DataProviderService,
	) {
		let promise = dataProvider.getSlackAccountToDeactivateData();
		let self = this;
		promise.then(data => {
			self.data = data;
		});
	}
}
