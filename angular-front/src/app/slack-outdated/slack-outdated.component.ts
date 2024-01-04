import { Component } from '@angular/core';
import { TimestampedSlackUserList } from '../generated/api/model/timestampedSlackUserList';
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
		apiClient: DefaultService,
	) {
		let obs = apiClient.apiSlackAccountsToDeactivateGet();
		let self = this;
		obs.subscribe({
			next(data) {
				self.data = data;
				self.dataFreshnessAsString = new Date(data.timestamp*1000).toISOString();
			},
			error(err) {
				console.log("failed to load slack accounts to deactivate: " + JSON.stringify(err));
			}
		});
	}
}
