import { Component } from '@angular/core';
import { DefaultService } from './generated/api/api/default.service';
import { DefaultLoginService } from './generated/login/api/default.service';
import { LoginPostRequest} from './generated/login/model/loginPostRequest';
import { User } from './generated/login/model/user';
import { ApiMembersGet200ResponseInner } from './generated/api/model/apiMembersGet200ResponseInner';
import { Observable } from 'rxjs';
import { PasswordChangerComponent } from './password-changer/password-changer.component';
import { MembersListComponent } from './members-list/members-list.component';
import { LoginComponent } from './login/login.component';
import { NgIf, NgClass } from '@angular/common';

@Component({
	selector: 'app-root',
	templateUrl: './app.component.html',
	styleUrls: ['./app.component.css'],
	standalone: true,
	imports: [NgIf, LoginComponent, NgClass, PasswordChangerComponent, MembersListComponent]
})
export class AppComponent {
	loggedIn = false; // TODO: also get the name somehow?
	logoutInProgress = false;
	page = "members";
	membersLoaded: boolean = false;
	members: Array<ApiMembersGet200ResponseInner> = [];

	constructor(
		private apiClient: DefaultService,
		private loginClient: DefaultLoginService,
	) {}


	loginInitializedEventReceived() {
		this.loggedIn = true;
		this.fetchMembers();
	}

	setPage(page: string): void {
		this.page = page;
	}

	fetchMembers() {
		let obs: Observable<Array<ApiMembersGet200ResponseInner>> = this.apiClient.apiMembersGet();
		let self = this;
		obs.subscribe({
			next(members) {
				console.log("got " + members.length + " members");
				self.members = members.reverse();
				self.membersLoaded = true;
			},
			error(err) {
				console.log("failed to get members: " + JSON.stringify(err));
			}
		});

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
			},
			error(err) {
				self.logoutInProgress = false;
				console.log("Failed to logout: " + JSON.stringify(err));
			}
		});
	}

	passwordChangedSuccessfullyEventReceived() {
		this.page = "members";
	}
}
