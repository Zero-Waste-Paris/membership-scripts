import { Component } from '@angular/core';
import { TimestampedSlackUserList } from '../generated/api/model/timestampedSlackUserList';
import { DataProviderService } from '../data-provider.service';
import { DefaultService } from '../generated/api/api/default.service';
import { NgIf, NgFor } from '@angular/common';

@Component({
  selector: 'app-slack-outdated',
  standalone: true,
  imports: [NgIf, NgFor],
  templateUrl: './slack-outdated.component.html',
  styleUrl: './slack-outdated.component.css'
})
export class SlackOutdatedComponent {
	data: TimestampedSlackUserList|null = null;
	dataFreshnessAsString: string|null = null;

	constructor(
		dataProvider: DataProviderService,
	) {
		let promise = dataProvider.getSlackAccountToDeactivateData();
		let self = this;
		promise.then(data => {
			self.data = data;
			self.dataFreshnessAsString = new Date(data.timestamp*1000).toISOString();
		});
	}
}
