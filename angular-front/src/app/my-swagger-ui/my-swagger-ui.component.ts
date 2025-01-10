import { Component, OnInit } from '@angular/core';
import SwaggerUI from 'swagger-ui';

@Component({
  selector: 'app-my-swagger-ui',
  standalone: true,
  imports: [],
  templateUrl: './my-swagger-ui.component.html',
  styleUrl: './my-swagger-ui.component.css'
})
export class MySwaggerUiComponent implements OnInit {
ngOnInit(): void {
  this.setSwagger();
}

  public setSwagger() {
    SwaggerUI({
      url: "https://petstore.swagger.io/v2/swagger.json",
      dom_id: '#swagger'
    })
  }
}
