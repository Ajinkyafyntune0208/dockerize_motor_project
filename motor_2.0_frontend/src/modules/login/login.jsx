import React from "react";
import styled from "styled-components";
import { useForm } from "react-hook-form";
import * as yup from "yup";
import swal from "sweetalert";
import { yupResolver } from "@hookform/resolvers/yup";
import { userIdentifier, authPdf } from "modules/login/login.slice";

const schema = yup.object().shape({
  // email: yup.string().required().email(),
  password: yup.string().required(),
});

const LoginContainer = styled.div`
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  height: 100vh;
`;

const LoginForm = styled.form`
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 100%;
  max-width: 400px;
  padding: 20px;
  background-color: #fff;
  border-radius: 10px;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
`;

const LoginInputWrapper = styled.div`
  display: flex;
  flex-direction: column;
  width: 100%;
  margin-bottom: 10px;
`;

const LoginLabel = styled.label`
  font-size: 18px;
  font-weight: 500;
  margin-bottom: 5px;
`;

const LoginInput = styled.input`
  padding: 10px;
  font-size: 16px;
  border: none;
  background-color: #f5f5f5;
  border-radius: 5px;
  transition: all 0.2s ease-in-out;

  &:focus {
    outline: none;
    box-shadow: 0 0 5px rgba(0, 0, 0, 0.2);
  }
`;

const LoginButton = styled.button`
  padding: 10px;
  margin-top: 20px;
  font-size: 16px;
  background-color: #008080;
  color: #fff;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  transition: all 0.2s ease-in-out;

  &:hover {
    background-color: #006666;
  }
`;

export const Login = ({ rehit }) => {
  const {
    register,
    handleSubmit,
    formState: { errors },
  } = useForm({
    resolver: yupResolver(schema),
  });

  const onSubmit = (data) => {
    let creds = rehit ? authPdf : userIdentifier;
    if (creds.includes(atob(data?.password))) {
      swal("Login successful", "", "success").then(() => [
        localStorage.setItem(rehit ? "authRehit" : "configKey", data?.password),
        window.location.reload(),
      ]);
    } else {
      swal("Wrong credentials", "", "error");
    }
  };

  return (
    <LoginContainer>
      <LoginForm onSubmit={handleSubmit(onSubmit)}>
        <LoginInputWrapper>
          <LoginLabel htmlFor="password">Password</LoginLabel>
          <LoginInput
            type="password"
            id="password"
            ref={register}
            name="password"
            placeholder="Enter your password"
          />
          {errors.password && (
            <span style={{ color: "red" }}>{errors.password.message}</span>
          )}
        </LoginInputWrapper>
        <LoginButton type="submit">Sign In</LoginButton>
      </LoginForm>
    </LoginContainer>
  );
};
