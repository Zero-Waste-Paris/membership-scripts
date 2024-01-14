import { Component } from '@angular/core';
import { DataProviderService } from './data-provider.service';
import { DefaultService } from './generated/api/api/default.service';
import { DefaultLoginService } from './generated/login/api/default.service';
import { RouterModule } from '@angular/router';
import { LoginPostRequest} from './generated/login/model/loginPostRequest';
import { User } from './generated/login/model/user';
import { Observable } from 'rxjs';
import { PasswordChangerComponent } from './password-changer/password-changer.component';
import { SlackOutdatedComponent } from './slack-outdated/slack-outdated.component';
import { MembersListPageComponent } from './members-list-page/members-list-page.component';
import { LoginComponent } from './login/login.component';
import { NgIf, NgClass } from '@angular/common';

@Component({
	selector: 'app-root',
	templateUrl: './app.component.html',
	styleUrls: ['./app.component.css'],
	standalone: true,
	imports: [NgIf, LoginComponent, NgClass, PasswordChangerComponent, MembersListPageComponent, RouterModule, SlackOutdatedComponent]
})
export class AppComponent {
	loggedIn = false; // TODO: also get the name somehow?
	logoutInProgress = false;

	constructor(
		private loginClient: DefaultLoginService,
		private dataProvider: DataProviderService,
	) {}


	loginInitializedEventReceived() {
		this.loggedIn = true;
	}

	logout() {
		this.logoutInProgress = true;
		let obs: Observable<any> = this.loginClient.logoutPost();
		let self = this;
		obs.subscribe({
			next() {
				console.log("logout successful");
				self.loggedIn = false;
				self.logoutInProgress = false;
				self.dataProvider.clearData();
			},
			error(err) {
				self.logoutInProgress = false;
				console.log("Failed to logout: " + JSON.stringify(err));
			}
		});
	}
}
