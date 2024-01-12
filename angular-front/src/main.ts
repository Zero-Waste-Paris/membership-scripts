import { platformBrowserDynamic } from '@angular/platform-browser-dynamic';

import { importProvidersFrom } from '@angular/core';
import { AppComponent } from './app/app.component';
import { LoginApiModule } from './app/generated/login/api.module';
import { Configuration as LoginConfiguration } from './app/generated/login/configuration';
import { Configuration } from './app/generated/api/configuration';
import { ApiModule } from './app/generated/api/api.module';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { withInterceptorsFromDi, provideHttpClient } from '@angular/common/http';
import { BrowserModule, bootstrapApplication } from '@angular/platform-browser';
import { provideAnimations } from '@angular/platform-browser/animations';
import { provideRouter, RouterModule, Routes } from '@angular/router';
import { MembersListComponent } from './app/members-list/members-list.component';
import { PasswordChangerComponent } from './app/password-changer/password-changer.component';
import { SlackOutdatedComponent } from './app/slack-outdated/slack-outdated.component';

function clientConfigFactory(): Configuration {
	return new Configuration(buildClientsConfigParameters());
}
function loginClientConfigFactory(): LoginConfiguration {
	return new LoginConfiguration(buildClientsConfigParameters());
}
function buildClientsConfigParameters() {
	let host = window.location.host;
	let protocol = window.location.protocol;
	return {
		basePath: protocol + "//" + host
	}
}

const routes: Routes = [
	{ path: '', component: MembersListComponent },
	{ path: 'account', component: PasswordChangerComponent },
	{ path: 'unknown-slack-accounts', component: SlackOutdatedComponent },
]

bootstrapApplication(AppComponent, {
	providers: [
		importProvidersFrom(BrowserModule, FormsModule, ReactiveFormsModule, ApiModule.forRoot(clientConfigFactory), LoginApiModule.forRoot(loginClientConfigFactory)),
		provideHttpClient(withInterceptorsFromDi()),
		provideAnimations(),
		provideRouter(routes),
]
})
	.catch(err => console.error(err));
